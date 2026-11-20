<?php
// Dane demo (liceum)
function seed($p) {
    $h = password_hash('haslo123', PASSWORD_DEFAULT);
    $u = $p->prepare('INSERT INTO users(email,password,role,name) VALUES(?,?,?,?)');
    foreach ([['admin', 'admin', 'Adam Administrator'], ['dyrektor', 'dyrektor', 'Dorota Dyrektor'], ['sekretariat', 'sekretariat', 'Sylwia Sekretarz'], ['nauczyciel', 'nauczyciel', 'Anna Nowak'], ['wychowawca', 'wychowawca', 'Jan Kowalski'], ['rodzic', 'rodzic', 'Piotr Wiśniewski'], ['uczen', 'uczen', 'Maja Wiśniewska']] as $r)
        $u->execute([$r[0] . '@szkola.local', $h, $r[1], $r[2]]);
    $p->exec("INSERT INTO classes(name,homeroom_id) VALUES('1A',5),('1B',NULL)");
    $s = $p->prepare('INSERT INTO students(user_id,first,last,birth,pesel,address,class_id) VALUES(?,?,?,?,?,?,?)');
    $st = [[7, 'Maja', 'Wiśniewska', 1], [null, 'Kacper', 'Zieliński', 1], [null, 'Zuzanna', 'Lewandowska', 1], [null, 'Antoni', 'Kamiński', 1], [null, 'Julia', 'Szymańska', 1], [null, 'Szymon', 'Dąbrowski', 1], [null, 'Hanna', 'Woźniak', 2], [null, 'Filip', 'Mazur', 2], [null, 'Lena', 'Krawczyk', 2]];
    foreach ($st as $i => $r) {
        $s->execute([$r[0], $r[1], $r[2], '2010-0' . (1+$i%9) . '-1' . ($i%9), '1000' . sprintf('%07d', 1234567+$i*111), 'ul. Licealna ' . ($i+1) . ', Katowice', $r[3]]);
        $p->prepare("INSERT INTO class_history(student_id,class_id,from_date) VALUES(?,?,'2025-09-01')")->execute([$i+1, $r[3]]);
    }
    $p->exec("INSERT INTO subjects(name) VALUES('Język polski'),('Matematyka'),('Język angielski'),('Fizyka'),('Historia'),('Biologia'),('Informatyka'),('Wychowanie fizyczne')");
    $p->exec("INSERT INTO teacher_subjects VALUES(4,2),(4,4),(5,1),(5,3),(5,5)");
    $p->exec("INSERT INTO parent_links(parent_id,student_id,relation,contact,pickup,restricted) VALUES(6,1,'ojciec','600100200',1,0)");
    $p->exec("INSERT INTO assignments(teacher_id,class_id,subject_id) VALUES(5,1,1),(5,1,3),(4,1,2),(4,1,4),(4,2,2)");
    $p->exec("INSERT INTO schedule(assignment_id,weekday,lesson_no) VALUES(1,1,1),(1,2,1),(1,3,1),(2,1,2),(2,3,2),(3,1,3),(3,4,2),(4,2,3),(5,2,2),(5,4,1)");
    // dane demo frekwencji (ostatnie ~3 tygodnie, Matematyka 1A)
    $ss = ['obecny', 'obecny', 'obecny', 'obecny', 'spóźniony', 'nieobecny', 'usprawiedliwiony', 'zwolniony'];
    for ($d = 21;$d >= 1;$d--) {
        $t = strtotime("-$d day");
        if (date('N', $t) > 5)
            continue;
        $date = date('Y-m-d', $t);
        $p->prepare('INSERT INTO lessons(assignment_id,date,lesson_no,topic) VALUES(3,?,3,?)')->execute([$date, 'Temat demo: funkcja kwadratowa (' . $date . ')']);
        $lid = $p->lastInsertId();
        for ($sid = 1;$sid <= 6;$sid++)
            $p->prepare('INSERT INTO attendance VALUES(?,?,?)')->execute([$lid, $sid, $ss[($sid*sid_mix($sid)+$d*3)%8]]);
    }
    seedGrades($p);
    seedDemoFixture($p);
    seedDemoAllClasses($p);
}
function seedGrades($p) {
    // demo: kilka ocen cząstkowych i rocznych dla uczniów klas 1A
    $subjects = [1, 2, 3, 4, 5];
    $g = $p->prepare("INSERT INTO grades(student_id,subject_id,teacher_id,value,kind,date) VALUES(?,?,?,?,?,date('now'))");
    foreach (range(1, 6) as $sid) {
        foreach ($subjects as $sub) {
            $teacher = in_array($sub, [2, 4]) ? 4 : 5;
            foreach ([3, 4, 5] as $k => $base)
                $g->execute([$sid, $sub, $teacher, max(1, min(6, $base+(($sid+$sub+$k)%3)-($sid == 4 && $sub == 2 ? 5 : 0))), 'cząstkowa']);
        }
    }
    $p->exec("INSERT INTO remarks(student_id,teacher_id,text,date) VALUES(2,4,'Rozmowa na lekcji',date('now')),(2,5,'Brak zadania',date('now')),(4,4,'Spóźnienie',date('now'))");
    $p->exec("UPDATE students SET conduct='wzorowe' WHERE id IN(1,3)");
}
function sid_mix($s) {
    return $s%3+1;
}

function seedDemoFixture($p) {
    $targetSubject = 'Projektowanie aplikacji desktopowych';
    $existingTarget = $p->prepare('SELECT id FROM subjects WHERE name=?');
    $existingTarget->execute([$targetSubject]);
    if (!$existingTarget->fetchColumn()) {
        $p->exec("UPDATE subjects SET name='Projektowanie aplikacji desktopowych' WHERE lower(trim(name))='pades'");
    }

    if (!function_exists('seedDemoAllClasses')) {
    function seedDemoAllClasses($p) {
        $fixture = 'demo-all-classes-v1';
        $marker = $p->prepare('SELECT 1 FROM migration_runs WHERE name=?');
        $marker->execute([$fixture]);
        if ($marker->fetchColumn()) {
            return;
        }

        $ownsTransaction = !$p->inTransaction();
        if ($ownsTransaction) {
            $p->beginTransaction();
        }
        try {
            $lookupUser = $p->prepare('SELECT id FROM users WHERE email=?');
            $lookupUser->execute(['demo.rodzic@szkola.local']);
            $parentId = (int)$lookupUser->fetchColumn();
            $lookupUser->execute(['demo.nauczyciel@szkola.local']);
            $teacherId = (int)$lookupUser->fetchColumn();
            $targetSubject = 'Projektowanie aplikacji desktopowych';
            $subjectLookup = $p->prepare('SELECT id FROM subjects WHERE name=?');
            $subjectLookup->execute([$targetSubject]);
            $targetSubjectId = (int)$subjectLookup->fetchColumn();
            $subjectLookup->execute(['Matematyka']);
            $mathSubjectId = (int)$subjectLookup->fetchColumn();

            $groupTeacherEmail = 'demo.nauczyciel.grupa@szkola.local';
            $p->prepare('INSERT OR IGNORE INTO users(email,password,role,name) VALUES(?,?,?,?)')->execute([
                $groupTeacherEmail,
                password_hash('demo12345', PASSWORD_DEFAULT),
                'nauczyciel',
                'Tomasz Demo',
            ]);
            $lookupUser->execute([$groupTeacherEmail]);
            $groupTeacherId = (int)$lookupUser->fetchColumn();

            $demoClass = $p->prepare("SELECT id FROM classes WHERE name='DEMO - 1A'");
            $demoClass->execute();
            $demoClassId = (int)$demoClass->fetchColumn();
            if ($demoClassId && $targetSubjectId) {
                $p->prepare('INSERT OR IGNORE INTO teacher_subjects(user_id,subject_id) VALUES(?,?)')->execute([$groupTeacherId, $targetSubjectId]);
                $p->prepare('INSERT OR IGNORE INTO assignments(teacher_id,class_id,subject_id) VALUES(?,?,?)')->execute([$groupTeacherId, $demoClassId, $targetSubjectId]);
                $assignment = $p->prepare('SELECT id FROM assignments WHERE teacher_id=? AND class_id=? AND subject_id=?');
                $assignment->execute([$teacherId, $demoClassId, $targetSubjectId]);
                $firstAssignmentId = (int)$assignment->fetchColumn();
                $assignment->execute([$groupTeacherId, $demoClassId, $targetSubjectId]);
                $secondAssignmentId = (int)$assignment->fetchColumn();
                $p->prepare("INSERT INTO schedule(assignment_id,weekday,lesson_no,group_name) SELECT ?,1,2,'Grupa B' WHERE NOT EXISTS(SELECT 1 FROM schedule WHERE assignment_id=? AND weekday=1 AND lesson_no=2)")->execute([$secondAssignmentId, $secondAssignmentId]);
                $p->prepare("UPDATE schedule SET group_name='Grupa A' WHERE assignment_id=? AND weekday=1 AND lesson_no=2 AND group_name=''")->execute([$firstAssignmentId]);
            }

            $classes = $p->query('SELECT id,name FROM classes ORDER BY id')->fetchAll();
            $studentLookup = $p->prepare('SELECT id FROM students WHERE class_id=? AND last=?');
            $studentInsert = $p->prepare('INSERT INTO students(user_id,first,last,birth,pesel,address,class_id) VALUES(NULL,?,?,?,?,?,?)');
            $parentLink = $p->prepare("INSERT INTO parent_links(parent_id,student_id,relation,contact,pickup,restricted) VALUES(?,?,'opiekun','demo@example.invalid',1,0) ON CONFLICT(parent_id,student_id) DO NOTHING");
            $history = $p->prepare('INSERT INTO class_history(student_id,class_id,from_date) SELECT ?,?,date(\'now\') WHERE NOT EXISTS(SELECT 1 FROM class_history WHERE student_id=? AND class_id=?)');
            $assignmentRows = $p->prepare('SELECT a.id,a.teacher_id,a.subject_id FROM assignments a WHERE a.class_id=? ORDER BY a.id');
            $scheduleRows = $p->prepare('SELECT sc.id,sc.assignment_id,sc.weekday,sc.lesson_no FROM schedule sc JOIN assignments a ON a.id=sc.assignment_id WHERE a.class_id=? ORDER BY sc.weekday,sc.lesson_no,sc.id');

            foreach ($classes as $class) {
                $classId = (int)$class['id'];
                $studentIds = [];
                foreach ([['Alicja', 'Testowa'], ['Bartek', 'Testowy']] as $index => [$first, $last]) {
                    $demoLast = $last . '-' . sprintf('%02d', $classId) . '-' . ($index + 1);
                    $studentLookup->execute([$classId, $demoLast]);
                    $studentId = $studentLookup->fetchColumn();
                    if (!$studentId) {
                        $month = str_pad((string)(1 + (($classId + $index) % 12)), 2, '0', STR_PAD_LEFT);
                        $day = str_pad((string)(1 + (($classId * 3 + $index) % 27)), 2, '0', STR_PAD_LEFT);
                        $studentInsert->execute([$first, $demoLast, '2010-' . $month . '-' . $day, '', '', $classId]);
                        $studentId = (int)$p->lastInsertId();
                    }
                    $studentId = (int)$studentId;
                    $studentIds[] = $studentId;
                    $parentLink->execute([$parentId, $studentId]);
                    $history->execute([$studentId, $classId, $studentId, $classId]);
                }

                $assignmentRows->execute([$classId]);
                $assignments = $assignmentRows->fetchAll();
                if (!$assignments && $targetSubjectId && $mathSubjectId) {
                    $teacherEmail = 'demo.klasa.' . $classId . '@szkola.local';
                    $p->prepare('INSERT OR IGNORE INTO users(email,password,role,name) VALUES(?,?,?,?)')->execute([
                        $teacherEmail,
                        password_hash('demo12345', PASSWORD_DEFAULT),
                        'nauczyciel',
                        'Nauczyciel DEMO ' . $classId,
                    ]);
                    $lookupUser->execute([$teacherEmail]);
                    $classTeacherId = (int)$lookupUser->fetchColumn();
                    $p->prepare('INSERT OR IGNORE INTO assignments(teacher_id,class_id,subject_id) VALUES(?,?,?)')->execute([$classTeacherId, $classId, $targetSubjectId]);
                    $assignmentRows->execute([$classId]);
                    $assignments = $assignmentRows->fetchAll();
                }
                if (!$assignments) {
                    continue;
                }

                $scheduleRows->execute([$classId]);
                $schedule = $scheduleRows->fetchAll();
                if (!$schedule) {
                    $insertSchedule = $p->prepare('INSERT INTO schedule(assignment_id,weekday,lesson_no) VALUES(?,?,?)');
                    foreach (range(1, 5) as $weekday) {
                        $added = false;
                        foreach (range(1, 10) as $lessonNo) {
                            foreach ($assignments as $assignment) {
                                $busy = $p->prepare('SELECT 1 FROM schedule sc JOIN assignments a ON a.id=sc.assignment_id WHERE sc.weekday=? AND sc.lesson_no=? AND (a.class_id=? OR a.teacher_id=?) LIMIT 1');
                                $busy->execute([$weekday, $lessonNo, $classId, $assignment['teacher_id']]);
                                if (!$busy->fetchColumn()) {
                                    $insertSchedule->execute([$assignment['id'], $weekday, $lessonNo]);
                                    $schedule[] = ['id' => (int)$p->lastInsertId(), 'assignment_id' => $assignment['id'], 'weekday' => $weekday, 'lesson_no' => $lessonNo];
                                    $added = true;
                                    break 2;
                                }
                            }
                        }
                        if (!$added) {
                            break;
                        }
                    }
                }

                $lessons = [];
                foreach ($schedule as $slot) {
                    $weekday = (int)$slot['weekday'];
                    if ($weekday < 1 || $weekday > 5 || isset($lessons[$weekday])) {
                        continue;
                    }
                    $todayWeekday = (int)date('N');
                    $offset = ($todayWeekday - $weekday + 7) % 7;
                    if ($offset === 0) {
                        $offset = 7;
                    }
                    $date = date('Y-m-d', strtotime('-' . $offset . ' days'));
                    $p->prepare('INSERT OR IGNORE INTO lessons(assignment_id,date,lesson_no,topic) VALUES(?,?,?,?)')->execute([
                        $slot['assignment_id'],
                        $date,
                        $slot['lesson_no'],
                        'DEMO: przykładowa lekcja dla klasy ' . $class['name'],
                    ]);
                    $lessonLookup = $p->prepare('SELECT id FROM lessons WHERE assignment_id=? AND date=? AND lesson_no=?');
                    $lessonLookup->execute([$slot['assignment_id'], $date, $slot['lesson_no']]);
                    $lessonId = (int)$lessonLookup->fetchColumn();
                    foreach ($studentIds as $index => $studentId) {
                        $status = ['obecny', 'spóźniony', 'nieobecny', 'zwolniony'][($weekday + $index) % 4];
                        $p->prepare('INSERT OR IGNORE INTO attendance(lesson_id,student_id,status) VALUES(?,?,?)')->execute([$lessonId, $studentId, $status]);
                    }
                    $lessons[$weekday] = true;
                }

                foreach (array_slice($assignments, 0, 3) as $assignmentIndex => $assignment) {
                    foreach ($studentIds as $studentIndex => $studentId) {
                        $value = 3 + (($classId + $assignmentIndex + $studentIndex) % 4);
                        $p->prepare("INSERT INTO grades(student_id,subject_id,teacher_id,value,kind,date) VALUES(?,?,?,?,'cząstkowa',date('now',?))")->execute([
                            $studentId,
                            $assignment['subject_id'],
                            $assignment['teacher_id'],
                            $value,
                            '-' . (1 + $assignmentIndex + $studentIndex) . ' days',
                        ]);
                    }
                }
                $p->prepare("INSERT INTO remarks(student_id,teacher_id,text,date) VALUES(?,?,?,date('now','-1 day'))")->execute([
                    $studentIds[0],
                    $assignments[0]['teacher_id'],
                    'DEMO: przykładowa informacja o pracy ucznia.',
                ]);
                $p->prepare("INSERT INTO excuses(student_id,parent_id,date_from,date_to,reason,status,created) VALUES(?,?,date('now','-4 days'),date('now','-4 days'),'DEMO: usprawiedliwienie testowe','oczekuje',datetime('now','localtime'))")->execute([
                    $studentIds[0],
                    $parentId,
                ]);
            }

            $overlaps = $p->query("SELECT a.class_id,sc.weekday,sc.lesson_no,sc.id,sc.group_name FROM schedule sc JOIN assignments a ON a.id=sc.assignment_id WHERE (SELECT COUNT(*) FROM schedule sc2 JOIN assignments a2 ON a2.id=sc2.assignment_id WHERE a2.class_id=a.class_id AND sc2.weekday=sc.weekday AND sc2.lesson_no=sc.lesson_no)>1 ORDER BY a.class_id,sc.weekday,sc.lesson_no,sc.id")->fetchAll();
            $groupSlots = [];
            foreach ($overlaps as $slot) {
                $key = $slot['class_id'] . ':' . $slot['weekday'] . ':' . $slot['lesson_no'];
                $groupSlots[$key][] = $slot;
            }
            $setGroup = $p->prepare('UPDATE schedule SET group_name=? WHERE id=?');
            foreach ($groupSlots as $slots) {
                $used = array_column(array_filter($slots, static fn($slot) => $slot['group_name'] !== ''), 'group_name');
                $index = 0;
                foreach ($slots as $slot) {
                    if ($slot['group_name'] !== '') {
                        continue;
                    }
                    do {
                        $groupName = 'Grupa ' . chr(65 + $index++);
                    } while (in_array($groupName, $used, true));
                    $used[] = $groupName;
                    $setGroup->execute([$groupName, $slot['id']]);
                }
            }

            $p->prepare('INSERT INTO migration_runs(name,status,details,run_at) VALUES(?,?,?,datetime(\'now\',\'localtime\'))')->execute([
                $fixture,
                'completed',
                count($classes) . ' klas otrzymało oznaczone dane demonstracyjne; równoległe lekcje oznaczono grupami.',
            ]);
            if ($ownsTransaction) {
                $p->commit();
            }
        } catch (Throwable $e) {
            if ($ownsTransaction && $p->inTransaction()) {
                $p->rollBack();
            }
            throw $e;
        }
    }
    }

    $fixture = 'demo-fixture-v1';
    $marker = $p->prepare('SELECT 1 FROM migration_runs WHERE name=?');
    $marker->execute([$fixture]);
    if ($marker->fetchColumn()) {
        return;
    }

    $ownsTransaction = !$p->inTransaction();
    if ($ownsTransaction) {
        $p->beginTransaction();
    }
    try {
        $password = password_hash('demo12345', PASSWORD_DEFAULT);
        $users = [
            ['demo.nauczyciel@szkola.local', 'Anna Demo', 'nauczyciel'],
            ['demo.rodzic@szkola.local', 'Patryk Testowy', 'rodzic'],
            ['demo.uczen@szkola.local', 'Alicja Testowa', 'uczen'],
        ];
        $userInsert = $p->prepare('INSERT OR IGNORE INTO users(email,password,role,name) VALUES(?,?,?,?)');
        $userLookup = $p->prepare('SELECT id FROM users WHERE email=?');
        $userIds = [];
        foreach ($users as [$email, $name, $role]) {
            $userInsert->execute([$email, $password, $role, $name]);
            $userLookup->execute([$email]);
            $userIds[$role] = (int)$userLookup->fetchColumn();
        }

        $className = 'DEMO - 1A';
        $classInsert = $p->prepare('INSERT OR IGNORE INTO classes(name,homeroom_id) VALUES(?,?)');
        $classInsert->execute([$className, $userIds['nauczyciel']]);
        $classLookup = $p->prepare('SELECT id FROM classes WHERE name=?');
        $classLookup->execute([$className]);
        $classId = (int)$classLookup->fetchColumn();
        $p->prepare('UPDATE classes SET homeroom_id=? WHERE id=?')->execute([$userIds['nauczyciel'], $classId]);

        $studentInsert = $p->prepare('INSERT OR IGNORE INTO students(user_id,first,last,birth,pesel,address,class_id) VALUES(?,?,?,?,?,?,?)');
        $studentInsert->execute([$userIds['uczen'], 'Alicja', 'Testowa', '2010-03-15', '', '', $classId]);
        $studentInsert->execute([null, 'Bartek', 'Demo', '2010-06-22', '', '', $classId]);
        $studentInsert->execute([null, 'Celina', 'Testowa', '2010-09-08', '', '', $classId]);
        $studentLookup = $p->prepare('SELECT id FROM students WHERE user_id=?');
        $studentLookup->execute([$userIds['uczen']]);
        $studentIds = [(int)$studentLookup->fetchColumn()];
        $studentLookup = $p->prepare('SELECT id FROM students WHERE class_id=? AND user_id IS NULL ORDER BY id DESC LIMIT 2');
        $studentLookup->execute([$classId]);
        $studentIds = array_merge($studentIds, array_map('intval', $studentLookup->fetchAll(PDO::FETCH_COLUMN)));

        $history = $p->prepare("INSERT INTO class_history(student_id,class_id,from_date) VALUES(?,?,date('now'))");
        foreach ($studentIds as $studentId) {
            $history->execute([$studentId, $classId]);
        }

        $subjectInsert = $p->prepare('INSERT OR IGNORE INTO subjects(name) VALUES(?)');
        foreach ([$targetSubject, 'Matematyka'] as $subjectName) {
            $subjectInsert->execute([$subjectName]);
        }
        $subjectLookup = $p->prepare('SELECT id FROM subjects WHERE name=?');
        $subjectLookup->execute([$targetSubject]);
        $desktopSubjectId = (int)$subjectLookup->fetchColumn();
        $subjectLookup->execute(['Matematyka']);
        $mathSubjectId = (int)$subjectLookup->fetchColumn();

        $p->prepare('INSERT OR IGNORE INTO teacher_subjects(user_id,subject_id) VALUES(?,?)')->execute([$userIds['nauczyciel'], $desktopSubjectId]);
        $assignmentInsert = $p->prepare('INSERT OR IGNORE INTO assignments(teacher_id,class_id,subject_id) VALUES(?,?,?)');
        $assignmentInsert->execute([$userIds['nauczyciel'], $classId, $desktopSubjectId]);
        $assignmentInsert->execute([$userIds['nauczyciel'], $classId, $mathSubjectId]);
        $assignmentLookup = $p->prepare('SELECT id FROM assignments WHERE teacher_id=? AND class_id=? AND subject_id=?');
        $assignmentLookup->execute([$userIds['nauczyciel'], $classId, $desktopSubjectId]);
        $desktopAssignmentId = (int)$assignmentLookup->fetchColumn();
        $assignmentLookup->execute([$userIds['nauczyciel'], $classId, $mathSubjectId]);
        $mathAssignmentId = (int)$assignmentLookup->fetchColumn();

        $schedule = $p->prepare('INSERT INTO schedule(assignment_id,weekday,lesson_no) SELECT ?,?,? WHERE NOT EXISTS(SELECT 1 FROM schedule WHERE assignment_id=? AND weekday=? AND lesson_no=?)');
        foreach ([[1, 2], [2, 3], [3, 2], [4, 4], [5, 1]] as [$weekday, $lessonNo]) {
            $schedule->execute([$desktopAssignmentId, $weekday, $lessonNo, $desktopAssignmentId, $weekday, $lessonNo]);
        }
        $schedule->execute([$mathAssignmentId, 2, 1, $mathAssignmentId, 2, 1]);

        $lesson = $p->prepare('INSERT OR IGNORE INTO lessons(assignment_id,date,lesson_no,topic) VALUES(?,?,?,?)');
        $attendance = $p->prepare('INSERT OR IGNORE INTO attendance(lesson_id,student_id,status) VALUES(?,?,?)');
        $statuses = ['obecny', 'spóźniony', 'nieobecny'];
        for ($offset = 1; $offset <= 5; $offset++) {
            $date = date('Y-m-d', strtotime("-$offset days"));
            if ((int)date('N', strtotime($date)) > 5) {
                continue;
            }
            $lessonNo = 2;
            $lesson->execute([$desktopAssignmentId, $date, $lessonNo, 'Projekt aplikacji szkolnej — etap ' . (6 - $offset)]);
            $lessonLookup = $p->prepare('SELECT id FROM lessons WHERE assignment_id=? AND date=? AND lesson_no=?');
            $lessonLookup->execute([$desktopAssignmentId, $date, $lessonNo]);
            $lessonId = (int)$lessonLookup->fetchColumn();
            foreach ($studentIds as $index => $studentId) {
                $attendance->execute([$lessonId, $studentId, $statuses[($offset + $index) % count($statuses)]]);
            }
        }

        $grade = $p->prepare("INSERT INTO grades(student_id,subject_id,teacher_id,value,kind,date) VALUES(?,?,?,?,?,date('now',?))");
        foreach ($studentIds as $index => $studentId) {
            $grade->execute([$studentId, $desktopSubjectId, $userIds['nauczyciel'], 5 - ($index % 2), 'cząstkowa', '-' . ($index + 1) . ' days']);
            $grade->execute([$studentId, $mathSubjectId, $userIds['nauczyciel'], 4 + ($index % 2), 'cząstkowa', '-' . ($index + 2) . ' days']);
        }
        $p->prepare("INSERT INTO remarks(student_id,teacher_id,text,date) VALUES(?,?,?,date('now','-2 days'))")->execute([$studentIds[0], $userIds['nauczyciel'], 'Dobra współpraca w zespole projektowym.']);
        $p->prepare("INSERT INTO remarks(student_id,teacher_id,text,date) VALUES(?,?,?,date('now','-1 day'))")->execute([$studentIds[1], $userIds['nauczyciel'], 'Pamiętaj o uzupełnieniu dokumentacji projektu.']);

        $p->prepare("INSERT INTO parent_links(parent_id,student_id,relation,contact,pickup,restricted) VALUES(?,?, 'opiekun', 'demo@example.invalid',1,0) ON CONFLICT(parent_id,student_id) DO NOTHING")->execute([$userIds['rodzic'], $studentIds[0]]);
        $p->prepare("INSERT INTO excuses(student_id,parent_id,date_from,date_to,reason,status,created) VALUES(?,?,date('now','-3 days'),date('now','-3 days'),'Zwolnienie demonstracyjne','oczekuje',datetime('now','localtime'))")->execute([$studentIds[0], $userIds['rodzic']]);
        $p->prepare("INSERT INTO notifications(user_id,text,created) VALUES(?,?,datetime('now','localtime'))")->execute([$userIds['rodzic'], 'Przykładowe powiadomienie: dodano nową ocenę.']);
        $p->prepare("INSERT INTO notifications(user_id,text,created) VALUES(?,?,datetime('now','localtime'))")->execute([$userIds['uczen'], 'Przykładowe powiadomienie o zadaniu projektowym.']);
        $p->prepare("INSERT INTO messages(sender_id,recipient_id,subject,body,created_at) VALUES(?,?,?, ?,datetime('now','localtime'))")->execute([$userIds['nauczyciel'], $userIds['rodzic'], 'Projekt aplikacji', 'Uczniowie pracują nad prostą aplikacją desktopową. To wiadomość demonstracyjna.']);
        $p->prepare("INSERT INTO privacy_consents(user_id,purpose,consent,consent_text,created_at) VALUES(?,?,1,?,datetime('now','localtime'))")->execute([$userIds['rodzic'], 'Testowe powiadomienia', 'Wpis demonstracyjny — brak rzeczywistej zgody.']);

        $p->exec("INSERT INTO announcements(title,content,published_at,published) SELECT 'Ogłoszenie demonstracyjne','Przykładowa treść do sprawdzenia publikacji i widoku aktualności.',datetime('now','localtime'),1 WHERE NOT EXISTS(SELECT 1 FROM announcements WHERE title='Ogłoszenie demonstracyjne')");
        $p->exec("INSERT INTO events(title,event_date,event_time,end_date,description,location) SELECT 'Dzień projektów — wydarzenie testowe',date('now','+7 days'),'10:00',date('now','+7 days'),'Prezentacja projektów uczniowskich. Wydarzenie demonstracyjne.','DEMO - sala projektowa' WHERE NOT EXISTS(SELECT 1 FROM events WHERE title='Dzień projektów — wydarzenie testowe')");
        $p->exec("INSERT INTO gallery(title,image_url,description,created_at) SELECT 'Galeria demonstracyjna','assets/demo-school.svg','Grafika testowa — nie przedstawia prawdziwego wydarzenia.',datetime('now','localtime') WHERE NOT EXISTS(SELECT 1 FROM gallery WHERE title='Galeria demonstracyjna')");
        $p->exec("INSERT INTO documents(title,file_url,category,created_at) SELECT 'Informator demonstracyjny','assets/demo-school-guide.txt','Materiały testowe',datetime('now','localtime') WHERE NOT EXISTS(SELECT 1 FROM documents WHERE title='Informator demonstracyjny')");
        $p->exec("INSERT INTO meal_menus(menu_date,title,description) SELECT date('now','+1 day'),'Menu demonstracyjne','Zupa pomidorowa, ryż i warzywa — wpis testowy.' WHERE NOT EXISTS(SELECT 1 FROM meal_menus WHERE title='Menu demonstracyjne')");
        $p->exec("INSERT OR IGNORE INTO newsletter_subscribers(email,consent,created_at) VALUES('demo-newsletter@example.invalid',1,datetime('now','localtime'))");
        $p->prepare("INSERT INTO feedback(user_id,name,email,subject,message,status,created_at) VALUES(?,?,?,?,?,'nowe',datetime('now','localtime'))")->execute([$userIds['rodzic'], 'Patryk Testowy', 'demo@example.invalid', 'Zgłoszenie demonstracyjne', 'Przykładowa treść zgłoszenia do sprawdzenia panelu.']);
        $p->prepare("INSERT INTO migration_runs(name,status,details,run_at) VALUES(?,?,?,datetime('now','localtime'))")->execute([$fixture, 'completed', 'Dodano oznaczone konta i rekordy demonstracyjne.']);
        if ($ownsTransaction) {
            $p->commit();
        }
    } catch (Throwable $e) {
        if ($ownsTransaction && $p->inTransaction()) {
            $p->rollBack();
        }
        throw $e;
    }
}
