<?php
$root = dirname(__DIR__);

function check($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function freePort() {
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if (!$socket) {
        throw new RuntimeException("Nie można znaleźć wolnego portu: $error");
    }
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    return (int)substr(strrchr($address, ':'), 1);
}

class ApiClient {
    private $baseUrl;
    private $cookie = '';

    public function __construct($baseUrl) {
        $this->baseUrl = $baseUrl;
    }

    public function call($action, $payload = []) {
        $headers = "Content-Type: application/json\r\n";
        if ($this->cookie !== '') {
            $headers .= "Cookie: {$this->cookie}\r\n";
        }
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => $headers,
            'content' => json_encode($payload, JSON_THROW_ON_ERROR),
            'ignore_errors' => true,
            'timeout' => 5,
        ]]);
        $body = @file_get_contents($this->baseUrl . $action, false, $context);
        $responseHeaders = $http_response_header ?? [];
        if ($body === false) {
            throw new RuntimeException("Nie można połączyć się z API: $action");
        }
        foreach ($responseHeaders as $header) {
            if (preg_match('/^Set-Cookie:\s*([^;]+)/i', $header, $match)) {
                $this->cookie = $match[1];
            }
        }
        $status = 0;
        foreach ($responseHeaders as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $match)) {
                $status = (int)$match[1];
            }
        }
        $result = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        return [$status, $result];
    }
}

function expectOk($client, $action, $payload = []) {
    [$status, $result] = $client->call($action, $payload);
    check($status === 200, "$action zwróciło HTTP $status: " . json_encode($result, JSON_UNESCAPED_UNICODE));
    return $result;
}

function login($client, $email, $password, $role) {
    $result = expectOk($client, 'login', ['email' => $email, 'password' => $password]);
    check(($result['user']['role'] ?? null) === $role, "Nieprawidłowa rola konta $email");
}

function runSeeder($root, $env) {
    $pipes = [];
    $process = proc_open(
        [PHP_BINARY, $root . '/scripts/seed_demo.php'],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $root,
        $env
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Nie można uruchomić seedera danych demonstracyjnych');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    check($status === 0, "Seeder zakończył się błędem: $stderr");
    check(str_contains($stdout, 'Dane demonstracyjne gotowe.'), 'Seeder nie potwierdził zakończenia');
}

function removeTestDirectory($path) {
    if (!is_dir($path)) {
        return;
    }
    foreach (new DirectoryIterator($path) as $entry) {
        if ($entry->isDot()) {
            continue;
        }
        $entryPath = $entry->getPathname();
        if ($entry->isDir()) {
            removeTestDirectory($entryPath);
        } else {
            unlink($entryPath);
        }
    }
    rmdir($path);
}

$testRoot = sys_get_temp_dir() . '/school-auth-' . bin2hex(random_bytes(6));
$dataDir = $testRoot . '/private-data';
if (!mkdir($testRoot, 0700, true)) {
    throw new RuntimeException('Nie można utworzyć tymczasowego katalogu testowego');
}
$env = getenv();
$env['APP_DATA_DIR'] = $dataDir;
$port = freePort();
$descriptors = [
    0 => ['file', '/dev/null', 'r'],
    1 => ['file', '/dev/null', 'a'],
    2 => ['file', '/dev/null', 'a'],
];
$server = proc_open(
    [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', $root],
    $descriptors,
    $serverPipes,
    $root,
    $env
);
if (!is_resource($server)) {
    removeTestDirectory($testRoot);
    throw new RuntimeException('Nie można uruchomić testowego serwera PHP');
}

try {
    $client = new ApiClient("http://127.0.0.1:$port/api.php?action=");
    $ready = false;
    for ($attempt = 0; $attempt < 50; $attempt++) {
        try {
            expectOk($client, 'me');
            $ready = true;
            break;
        } catch (Throwable $error) {
            $status = proc_get_status($server);
            if (!$status['running']) {
                throw new RuntimeException('Serwer PHP zakończył się przed startem', 0, $error);
            }
            usleep(100000);
        }
    }
    check($ready, 'Testowy serwer PHP nie uruchomił się na czas');

    runSeeder($root, $env);
    runSeeder($root, $env);
    foreach ([
        ['demo.nauczyciel@szkola.local', 'nauczyciel'],
        ['demo.nauczyciel.grupa@szkola.local', 'nauczyciel'],
        ['demo.rodzic@szkola.local', 'rodzic'],
        ['demo.uczen@szkola.local', 'uczen'],
    ] as [$email, $role]) {
        login($client, $email, 'demo12345', $role);
        expectOk($client, 'logout');
    }

    login($client, 'admin@szkola.local', 'haslo123', 'admin');
    $classes = expectOk($client, 'classes_list');
    check(count($classes) >= 3, 'Brakuje klas w danych testowych');
    $demoClass = null;
    foreach ($classes as $class) {
        $students = expectOk($client, 'students_list', ['class_id' => $class['id']]);
        check(count($students) >= 2, "Klasa {$class['name']} nie ma przykładowych uczniów");
        $schedule = expectOk($client, 'schedule_list', ['class_id' => $class['id']]);
        check(count($schedule) > 0, "Klasa {$class['name']} nie ma planu lekcji");
        $slots = [];
        foreach ($schedule as $entry) {
            $key = $entry['weekday'] . ':' . $entry['lesson_no'];
            $slots[$key][] = $entry;
        }
        foreach ($slots as $entries) {
            if (count($entries) > 1) {
                $groups = array_column($entries, 'group_name');
                check(!in_array('', $groups, true), "Równoległe lekcje klasy {$class['name']} nie mają grup");
                check(count(array_unique($groups)) === count($groups), "Grupy lekcji w {$class['name']} nie są unikalne");
            }
        }
        if ($class['name'] === 'DEMO - 1A') {
            $demoClass = $class;
            $grouped = array_values(array_filter($schedule, static fn($entry) => $entry['weekday'] == 1 && $entry['lesson_no'] == 2));
            check(count($grouped) === 2, 'Brakuje dwóch równoległych zajęć demonstracyjnych');
            check(array_column($grouped, 'group_name') === ['Grupa A', 'Grupa B'], 'Nieprawidłowe oznaczenia grup demonstracyjnych');
        }
    }
    check($demoClass !== null, 'Brakuje klasy demonstracyjnej');
    $studentList = expectOk($client, 'students_list');
    $accountStudent = null;
    foreach ($studentList as $student) {
        if (empty($student['user_id'])) {
            $accountStudent = $student;
            break;
        }
    }
    check($accountStudent !== null, 'Brakuje ucznia do testu tworzenia konta');
    expectOk($client, 'user_save', [
        'email' => 'new-student@example.invalid',
        'name' => 'Uczeń testowy',
        'role' => 'uczen',
        'password' => 'test-password-123',
        'student_id' => $accountStudent['id'],
    ]);
    expectOk($client, 'logout');
    login($client, 'new-student@example.invalid', 'test-password-123', 'uczen');
    $linkedStudents = expectOk($client, 'students_list');
    check(count($linkedStudents) === 1 && (int)$linkedStudents[0]['id'] === (int)$accountStudent['id'], 'Nowe konto ucznia nie zostało powiązane z rekordem ucznia');
    expectOk($client, 'logout');

    login($client, 'admin@szkola.local', 'haslo123', 'admin');
    $roles = ['admin', 'dyrektor', 'sekretariat', 'nauczyciel', 'wychowawca', 'rodzic'];
    foreach ($roles as $index => $role) {
        $email = "new-$role-$index@example.invalid";
        expectOk($client, 'user_save', [
            'email' => $email,
            'name' => "Konto testowe $role",
            'role' => $role,
            'password' => 'test-password-123',
        ]);
        expectOk($client, 'logout');
        login($client, $email, 'test-password-123', $role);
        expectOk($client, 'logout');
        login($client, 'admin@szkola.local', 'haslo123', 'admin');
    }
    [$status, $registration] = $client->call('register');
    check($status === 404 && ($registration['error'] ?? '') === 'Nieznana akcja', 'Nieoczekiwany endpoint rejestracji publicznej');
    expectOk($client, 'logout');

    foreach ([
        ['demo.rodzic@szkola.local', 'rodzic'],
        ['demo.uczen@szkola.local', 'uczen'],
    ] as [$email, $role]) {
        login($client, $email, 'demo12345', $role);
        $children = expectOk($client, 'students_list');
        if ($role === 'rodzic') {
            check(count($children) >= count($classes) * 2, 'Rodzic nie widzi danych testowych wszystkich klas');
            foreach ($children as $student) {
                if (!str_contains($student['last'], 'Testow')) {
                    continue;
                }
                $dashboard = expectOk($client, 'family_dashboard', ['student_id' => $student['id']]);
                check($dashboard['grades'] && $dashboard['attendance'], "Brakuje ocen lub frekwencji dla ucznia {$student['last']}");
            }
        } else {
            check(count($children) === 1, 'Uczeń ma dostęp do rekordu innego ucznia');
            $dashboard = expectOk($client, 'family_dashboard', ['student_id' => $children[0]['id']]);
            check($dashboard['grades'] && $dashboard['attendance'], 'Uczeń nie widzi swoich ocen lub frekwencji');
        }
        expectOk($client, 'logout');
    }

    login($client, 'rodzic@szkola.local', 'haslo123', 'rodzic');
    $ownStudents = expectOk($client, 'students_list');
    check(count($ownStudents) === 1 && (int)$ownStudents[0]['id'] === 1, 'Odczyt rodzica nie jest ograniczony do powiązanego ucznia');
    [$status, $denied] = $client->call('family_dashboard', ['student_id' => 2]);
    check($status === 403 && isset($denied['error']), 'Rodzic może odczytać niepowiązanego ucznia');
    expectOk($client, 'logout');

    login($client, 'nauczyciel@szkola.local', 'haslo123', 'nauczyciel');
    $assignments = expectOk($client, 'my_assignments');
    $mathAssignment = null;
    foreach ($assignments as $assignment) {
        if ($assignment['class'] === '1A' && $assignment['subject'] === 'Matematyka') {
            $mathAssignment = $assignment;
            break;
        }
    }
    check($mathAssignment !== null, 'Brakuje przydziału do testu oceny');
    expectOk($client, 'grade_save', [
        'student_id' => 1,
        'subject_id' => 2,
        'value' => 6,
        'kind' => 'cząstkowa',
    ]);
    expectOk($client, 'remark_save', ['student_id' => 1, 'text' => 'Testowa uwaga integracyjna']);
    expectOk($client, 'logout');

    foreach ([
        ['rodzic@szkola.local', 'rodzic'],
        ['uczen@szkola.local', 'uczen'],
    ] as [$email, $role]) {
        login($client, $email, 'haslo123', $role);
        $grades = expectOk($client, 'student_grades', ['student_id' => 1]);
        check((bool)array_filter($grades['grades'], static fn($grade) => $grade['subject'] === 'Matematyka' && (int)$grade['value'] === 6), "$role nie widzi nowej oceny");
        check((bool)array_filter($grades['remarks'], static fn($remark) => str_contains($remark['text'], 'Testowa uwaga')), "$role nie widzi nowej uwagi");
        $notifications = expectOk($client, 'notif_list');
        check((bool)array_filter($notifications, static fn($item) => str_contains($item['text'], 'Nowa ocena z Matematyka')), "$role nie dostał powiadomienia o ocenie");
        expectOk($client, 'logout');
    }
} finally {
    proc_terminate($server);
    proc_close($server);
    removeTestDirectory($testRoot);
}

echo "Testy integracyjne PHP: logowanie, konta wszystkich ról, grupy planu i dane klas OK\n";
