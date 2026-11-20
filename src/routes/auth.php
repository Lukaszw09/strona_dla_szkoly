<?php
// Uwierzytelnianie i użytkownicy (TEST-16)
function route_auth($A) {
    global $U;
    switch ($A) {
        // ---------- Uwierzytelnianie (TEST-16) ----------
        case 'me':
            meOut();
        case 'login':
            $u = one('SELECT * FROM users WHERE email=?', [strtolower(trim(g('email', '')))]);
            if ($u && $u['locked']) {
                logit('LOGIN_LOCKED', $u['email']);
                err('Konto zablokowane po 5 nieudanych próbach. Skontaktuj się z administratorem.', 423);
            }
            if (!$u || !password_verify((string)g('password', ''), $u['password'])) {
                if ($u)
                    q('UPDATE users SET failed=failed+1,locked=(failed+1>=5) WHERE id=?', [$u['id']]);
                logit('LOGIN_FAIL', (string)g('email'));
                err('Nieprawidłowy e-mail lub hasło', 401);
            }
            q('UPDATE users SET failed=0 WHERE id=?', [$u['id']]);
            session_regenerate_id(true);
            $U = $_SESSION['u'] = ['id' => (int)$u['id'], 'role' => $u['role'], 'name' => $u['name'], 'email' => $u['email']];
            $_SESSION['last'] = time();
            logit('LOGIN_OK');
            meOut();
        case 'logout':
            logit('LOGOUT');
            session_destroy();
            out(['ok' => 1]);
        case 'reset_request':
            $e = strtolower(trim(g('email', '')));
            if (one('SELECT 1 FROM users WHERE email=?', [$e])) {
                $t = bin2hex(random_bytes(16));
                q('DELETE FROM reset_tokens WHERE email=?', [$e]);
                q('INSERT INTO reset_tokens VALUES(?,?,?)', [$e, hash('sha256', $t), time()+3600]);
                $m = "Reset hasła dla $e. Token: $t (ważny 1 godzinę)";
                if (!@mail($e, 'Reset hasla', $m))
                    file_put_contents(storageDir() . DIRECTORY_SEPARATOR . 'mail.log', date('c') . " $m\n", FILE_APPEND);
                logit('RESET_REQUEST', $e);
            }
            out(['ok' => 1]);
        case 'reset_confirm':
            $pw = (string)g('password', '');
            if (strlen($pw) < 8)
                err('Hasło musi mieć min. 8 znaków');
            $r = one('SELECT * FROM reset_tokens WHERE token=? AND expires>?', [hash('sha256', (string)g('token', '')), time()]);
            if (!$r)
                err('Token jest nieprawidłowy lub wygasł');
            q('UPDATE users SET password=?,failed=0,locked=0 WHERE email=?', [password_hash($pw, PASSWORD_DEFAULT), $r['email']]);
            q('DELETE FROM reset_tokens WHERE email=?', [$r['email']]);
            logit('RESET_DONE', $r['email']);
            out(['ok' => 1]);
        case 'users_list':
            need('admin');
            out(all('SELECT u.id,u.email,u.role,u.name,u.locked,(SELECT s.id FROM students s WHERE s.user_id=u.id ORDER BY s.id LIMIT 1) student_id FROM users u ORDER BY u.role,u.name'));
        case 'user_save':
            need('admin', 'sekretariat');
            $role = g('role');
            if (!in_array($role, ROLES))
                err('Nieprawidłowa rola');
            $okRoles = ['nauczyciel', 'wychowawca', 'rodzic', 'uczen'];
            if ($U['role'] == 'sekretariat' && !in_array($role, $okRoles))
                err('Brak uprawnień do tej roli', 403);
            $email = strtolower(trim(g('email', '')));
            $name = trim(g('name', ''));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '')
                err('Podaj imię i nazwisko oraz poprawny e-mail');
            $pw = (string)g('password', '');
            if ($pw !== '' && strlen($pw) < 8)
                err('Hasło musi mieć min. 8 znaków');
            $id = g('id');
            $studentId = trim((string)g('student_id', ''));
            if ($role === 'uczen') {
                if ($studentId === '' || !ctype_digit($studentId))
                    err('Wybierz ucznia, z którym połączyć konto');
                $linkedStudent = one('SELECT user_id FROM students WHERE id=?', [$studentId]);
                if (!$linkedStudent)
                    err('Nie znaleziono wybranego ucznia', 404);
                if ($linkedStudent['user_id'] && (int)$linkedStudent['user_id'] !== (int)$id)
                    err('Ten uczeń ma już przypisane konto');
            }
            try {
                if ($id) {
                    $old = one('SELECT role FROM users WHERE id=?', [$id]);
                    if (!$old)
                        err('Nie znaleziono', 404);
                    if ($U['role'] == 'sekretariat' && !in_array($old['role'], $okRoles))
                        err('Brak uprawnień', 403);
                    q('UPDATE users SET email=?,name=?,role=? WHERE id=?', [$email, $name, $role, $id]);
                    if ($pw !== '')
                        q('UPDATE users SET password=? WHERE id=?', [password_hash($pw, PASSWORD_DEFAULT), $id]);
                    if (g('unlock'))
                        q('UPDATE users SET failed=0,locked=0 WHERE id=?', [$id]);
                } else {
                    if ($pw === '')
                        err('Podaj hasło początkowe');
                    q('INSERT INTO users(email,password,role,name) VALUES(?,?,?,?)', [$email, password_hash($pw, PASSWORD_DEFAULT), $role, $name]);
                    $id = db()->lastInsertId();
                }
            } catch (PDOException $e) {
                err('Użytkownik z takim e-mailem już istnieje');
            }
            q('UPDATE students SET user_id=NULL WHERE user_id=?', [$id]);
            if ($role === 'uczen') {
                q('UPDATE students SET user_id=? WHERE id=?', [$id, $studentId]);
            }
            if (is_array(g('subjects'))) {
                q('DELETE FROM teacher_subjects WHERE user_id=?', [$id]);
                foreach (g('subjects') as $s)
                    q('INSERT OR IGNORE INTO teacher_subjects VALUES(?,?)', [$id, (int)$s]);
            }
            logit('user_save', "$email/$role");
            out(['id' => $id]);
        case 'log_list':
            need('admin', 'dyrektor');
            out(all('SELECT l.*,u.email FROM access_log l LEFT JOIN users u ON u.id=l.user_id ORDER BY l.id DESC LIMIT 300'));

        }
    }
