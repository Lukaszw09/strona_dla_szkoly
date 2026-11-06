<?php
require __DIR__.'/db.php';
session_set_cookie_params(['samesite'=>'Lax','httponly'=>true]);
session_start();
header('Content-Type: application/json; charset=utf-8');
const ST=['obecny','nieobecny','spóźniony','zwolniony','usprawiedliwiony'];
const ROLES=['admin','dyrektor','sekretariat','nauczyciel','wychowawca','rodzic','uczen'];
const MGMT=['admin','dyrektor','sekretariat'];
$in=json_decode(file_get_contents('php://input'),true)?:[];
$A=$_GET['action']??'';
function g($k,$d=null){global $in;return $in[$k]??$_GET[$k]??$d;}
function out($d,$c=200){http_response_code($c);echo json_encode($d,JSON_UNESCAPED_UNICODE);exit;}
function err($m,$c=400){out(['error'=>$m],$c);}
function q($s,$a=[]){$x=db()->prepare($s);$x->execute($a);return $x;}
function all($s,$a=[]){return q($s,$a)->fetchAll();}
function one($s,$a=[]){return q($s,$a)->fetch();}
function logit($act,$det=''){global $U;q("INSERT INTO access_log(user_id,action,detail,ip,created) VALUES(?,?,?,?,datetime('now','localtime'))",[$U['id']??null,$act,$det,$_SERVER['REMOTE_ADDR']??'']);}
function notify($uid,$t){q("INSERT INTO notifications(user_id,text,created) VALUES(?,?,datetime('now','localtime'))",[$uid,$t]);}
// sesja wygasa po 30 min bezczynności
if(isset($_SESSION['last'])&&time()-$_SESSION['last']>1800){session_unset();}
$U=$_SESSION['u']??null; if($U)$_SESSION['last']=time();
function need(...$roles){global $U,$A;if(!$U)err('Zaloguj się',401);if($roles&&!in_array($U['role'],$roles)){logit('DENIED',$A);err('Brak uprawnień',403);}}
function isT(){global $U;return in_array($U['role'],['nauczyciel','wychowawca']);}
function homeIds(){global $U;return array_column(all('SELECT id FROM classes WHERE homeroom_id=?',[$U['id']]),'id');}
function myClassIds(){global $U;return array_unique(array_merge(homeIds(),array_column(all('SELECT class_id FROM assignments WHERE teacher_id=?',[$U['id']]),'class_id')));}
function canStudent($sid){global $U;$s=one('SELECT * FROM students WHERE id=?',[$sid]);if(!$s)return false;$r=$U['role'];
  if(in_array($r,MGMT))return true;
  if($r=='rodzic')return (bool)one('SELECT 1 FROM parent_links WHERE parent_id=? AND student_id=? AND restricted=0',[$U['id'],$sid]);
  if($r=='uczen')return $s['user_id']==$U['id'];
  return in_array($s['class_id'],myClassIds());}
function needStudent($sid){if(!canStudent($sid)){logit('DENIED','student '.$sid);err('Brak dostępu do tego ucznia',403);}}
function isHomeroomOf($sid){$s=one('SELECT class_id FROM students WHERE id=?',[$sid]);return $s&&in_array($s['class_id'],homeIds());}
function validDate($d){return is_string($d)&&preg_match('/^\d{4}-\d{2}-\d{2}$/',$d)&&strtotime($d);}
function meOut(){global $U;out(['user'=>$U,'unread'=>$U?(int)one('SELECT COUNT(*) c FROM notifications WHERE user_id=? AND is_read=0',[$U['id']])['c']:0]);}

try{ switch($A){
// ---------- Uwierzytelnianie (TEST-16) ----------
case 'me': meOut();
case 'login':
  $u=one('SELECT * FROM users WHERE email=?',[strtolower(trim(g('email','')))]);
  if($u&&$u['locked']){logit('LOGIN_LOCKED',$u['email']);err('Konto zablokowane po 5 nieudanych próbach. Skontaktuj się z administratorem.',423);}
  if(!$u||!password_verify((string)g('password',''),$u['password'])){
    if($u)q('UPDATE users SET failed=failed+1,locked=(failed+1>=5) WHERE id=?',[$u['id']]);
    logit('LOGIN_FAIL',(string)g('email'));err('Nieprawidłowy e-mail lub hasło',401);}
  q('UPDATE users SET failed=0 WHERE id=?',[$u['id']]);
  session_regenerate_id(true);
  $U=$_SESSION['u']=['id'=>(int)$u['id'],'role'=>$u['role'],'name'=>$u['name'],'email'=>$u['email']];$_SESSION['last']=time();
  logit('LOGIN_OK');meOut();
case 'logout': logit('LOGOUT');session_destroy();out(['ok'=>1]);
case 'reset_request':
  $e=strtolower(trim(g('email','')));
  if(one('SELECT 1 FROM users WHERE email=?',[$e])){
    $t=bin2hex(random_bytes(16));q('DELETE FROM reset_tokens WHERE email=?',[$e]);
    q('INSERT INTO reset_tokens VALUES(?,?,?)',[$e,hash('sha256',$t),time()+3600]);
    $m="Reset hasła dla $e. Token: $t (ważny 1 godzinę)";
    if(!@mail($e,'Reset hasla',$m))file_put_contents(__DIR__.'/data/mail.log',date('c')." $m\n",FILE_APPEND);
    logit('RESET_REQUEST',$e);}
  out(['ok'=>1]);
case 'reset_confirm':
  $pw=(string)g('password','');if(strlen($pw)<8)err('Hasło musi mieć min. 8 znaków');
  $r=one('SELECT * FROM reset_tokens WHERE token=? AND expires>?',[hash('sha256',(string)g('token','')),time()]);
  if(!$r)err('Token jest nieprawidłowy lub wygasł');
  q('UPDATE users SET password=?,failed=0,locked=0 WHERE email=?',[password_hash($pw,PASSWORD_DEFAULT),$r['email']]);
  q('DELETE FROM reset_tokens WHERE email=?',[$r['email']]);logit('RESET_DONE',$r['email']);out(['ok'=>1]);
case 'users_list': need('admin');out(all('SELECT id,email,role,name,locked FROM users ORDER BY role,name'));
case 'user_save': need('admin','sekretariat');
  $role=g('role');if(!in_array($role,ROLES))err('Nieprawidłowa rola');
  $okRoles=['nauczyciel','wychowawca','rodzic','uczen'];
  if($U['role']=='sekretariat'&&!in_array($role,$okRoles))err('Brak uprawnień do tej roli',403);
  $email=strtolower(trim(g('email','')));$name=trim(g('name',''));
  if(!filter_var($email,FILTER_VALIDATE_EMAIL)||$name==='')err('Podaj imię i nazwisko oraz poprawny e-mail');
  $pw=(string)g('password','');if($pw!==''&&strlen($pw)<8)err('Hasło musi mieć min. 8 znaków');
  $id=g('id');
  try{
    if($id){$old=one('SELECT role FROM users WHERE id=?',[$id]);if(!$old)err('Nie znaleziono',404);
      if($U['role']=='sekretariat'&&!in_array($old['role'],$okRoles))err('Brak uprawnień',403);
      q('UPDATE users SET email=?,name=?,role=? WHERE id=?',[$email,$name,$role,$id]);
      if($pw!=='')q('UPDATE users SET password=? WHERE id=?',[password_hash($pw,PASSWORD_DEFAULT),$id]);
      if(g('unlock'))q('UPDATE users SET failed=0,locked=0 WHERE id=?',[$id]);
    }else{if($pw==='')err('Podaj hasło początkowe');
      q('INSERT INTO users(email,password,role,name) VALUES(?,?,?,?)',[$email,password_hash($pw,PASSWORD_DEFAULT),$role,$name]);$id=db()->lastInsertId();}
  }catch(PDOException $e){err('Użytkownik z takim e-mailem już istnieje');}
  if(is_array(g('subjects'))){q('DELETE FROM teacher_subjects WHERE user_id=?',[$id]);foreach(g('subjects') as $s)q('INSERT OR IGNORE INTO teacher_subjects VALUES(?,?)',[$id,(int)$s]);}
  logit('user_save',"$email/$role");out(['id'=>$id]);
case 'log_list': need('admin','dyrektor');
  out(all('SELECT l.*,u.email FROM access_log l LEFT JOIN users u ON u.id=l.user_id ORDER BY l.id DESC LIMIT 300'));

// ---------- Dane szkoły (TEST-4, TEST-144) ----------
case 'classes_list': need('admin','dyrektor','sekretariat','nauczyciel','wychowawca');
  out(all('SELECT c.*,u.name homeroom,(SELECT COUNT(*) FROM students WHERE class_id=c.id) n FROM classes c LEFT JOIN users u ON u.id=c.homeroom_id ORDER BY c.name'));
case 'class_save': need('admin','sekretariat');
  $n=trim(g('name',''));if($n==='')err('Podaj nazwę klasy');$h=g('homeroom_id')?:null;
  try{if(g('id'))q('UPDATE classes SET name=?,homeroom_id=? WHERE id=?',[$n,$h,g('id')]);else q('INSERT INTO classes(name,homeroom_id) VALUES(?,?)',[$n,$h]);}
  catch(PDOException $e){err('Klasa o tej nazwie już istnieje');}
  logit('class_save',$n);out(['ok'=>1]);
case 'students_list': need();
  $qq=trim(g('q',''));$c=g('class_id');$res=[];
  foreach(all('SELECT s.*,c.name class FROM students s LEFT JOIN classes c ON c.id=s.class_id ORDER BY s.last,s.first') as $s){
    if(!canStudent($s['id']))continue;if($c&&$s['class_id']!=$c)continue;
    if($qq!==''&&mb_stripos($s['first'].' '.$s['last'].' '.$s['pesel'].' '.$s['class'],$qq)===false)continue;
    if(!in_array($U['role'],MGMT)){$s['pesel']=null;$s['address']=null;$s['birth']=null;}
    $res[]=$s;}
  logit('students_list',$qq);out($res);
case 'student_save': need('admin','sekretariat');
  $f=trim(g('first',''));$l=trim(g('last',''));$pe=trim(g('pesel',''));
  if($f===''||$l==='')err('Podaj imię i nazwisko ucznia');
  if($pe!==''&&!preg_match('/^\d{11}$/',$pe))err('PESEL musi mieć 11 cyfr');
  $c=g('class_id')?:null;
  if(g('id')){q('UPDATE students SET first=?,last=?,birth=?,pesel=?,address=? WHERE id=?',[$f,$l,g('birth'),$pe,g('address'),g('id')]);}
  else{q('INSERT INTO students(first,last,birth,pesel,address,class_id) VALUES(?,?,?,?,?,?)',[$f,$l,g('birth'),$pe,g('address'),$c]);
    if($c)q("INSERT INTO class_history(student_id,class_id,from_date) VALUES(?,?,date('now'))",[db()->lastInsertId(),$c]);}
  logit('student_save',"$f $l");out(['ok'=>1]);
case 'student_move': need('admin','sekretariat');
  if(!validDate(g('date')))err('Podaj datę zmiany');if(!one('SELECT 1 FROM classes WHERE id=?',[g('class_id')]))err('Wybierz klasę');
  q('UPDATE students SET class_id=? WHERE id=?',[g('class_id'),g('student_id')]);
  q('INSERT INTO class_history(student_id,class_id,from_date) VALUES(?,?,?)',[g('student_id'),g('class_id'),g('date')]);
  logit('student_move',(string)g('student_id'));out(['ok'=>1]); // oceny i frekwencja zostają przypisane do ucznia, więc historia się zachowuje
case 'teachers_list': need('admin','dyrektor','sekretariat');
  out(all("SELECT u.id,u.name,u.email,u.role,(SELECT group_concat(s.name,', ') FROM teacher_subjects t JOIN subjects s ON s.id=t.subject_id WHERE t.user_id=u.id) subjects FROM users u WHERE role IN('nauczyciel','wychowawca') ORDER BY u.name"));
case 'parents_list': need('admin','dyrektor','sekretariat');out(all("SELECT id,name,email FROM users WHERE role='rodzic' ORDER BY name"));
case 'links_list': need('admin','dyrektor','sekretariat');
  out(all('SELECT l.*,u.name parent,s.first||" "||s.last student FROM parent_links l JOIN users u ON u.id=l.parent_id JOIN students s ON s.id=l.student_id ORDER BY s.last'));
case 'link_save': need('admin','sekretariat');
  q('INSERT INTO parent_links(parent_id,student_id,relation,contact,pickup,restricted) VALUES(?,?,?,?,?,?) ON CONFLICT(parent_id,student_id) DO UPDATE SET relation=excluded.relation,contact=excluded.contact,pickup=excluded.pickup,restricted=excluded.restricted',
    [g('parent_id'),g('student_id'),g('relation'),g('contact'),g('pickup')?1:0,g('restricted')?1:0]);
  logit('link_save',g('parent_id').'->'.g('student_id'));out(['ok'=>1]);
case 'link_delete': need('admin','sekretariat');q('DELETE FROM parent_links WHERE id=?',[g('id')]);out(['ok'=>1]);
case 'subjects_list': need();out(all('SELECT * FROM subjects ORDER BY name'));
case 'subject_save': need('admin');$n=trim(g('name',''));if($n==='')err('Podaj nazwę przedmiotu');
  try{q('INSERT INTO subjects(name) VALUES(?)',[$n]);}catch(PDOException $e){err('Taki przedmiot już istnieje');}out(['ok'=>1]);
case 'assignments_list': need('admin','dyrektor','sekretariat');
  out(all('SELECT a.id,a.class_id,a.teacher_id,a.subject_id,c.name class,s.name subject,u.name teacher FROM assignments a JOIN classes c ON c.id=a.class_id JOIN subjects s ON s.id=a.subject_id JOIN users u ON u.id=a.teacher_id ORDER BY c.name,s.name'));
case 'assignment_save': need('admin','dyrektor');
  try{q('INSERT INTO assignments(teacher_id,class_id,subject_id) VALUES(?,?,?)',[g('teacher_id'),g('class_id'),g('subject_id')]);}catch(PDOException $e){err('Taki przydział już istnieje');}
  logit('assignment_save');out(['ok'=>1]);
case 'assignment_delete': need('admin','dyrektor');q('DELETE FROM schedule WHERE assignment_id=?',[g('id')]);q('DELETE FROM assignments WHERE id=?',[g('id')]);out(['ok'=>1]);
case 'schedule_list': need('admin','dyrektor','sekretariat','nauczyciel','wychowawca');
  out(all('SELECT sc.id,sc.weekday,sc.lesson_no,s.name subject,u.name teacher FROM schedule sc JOIN assignments a ON a.id=sc.assignment_id JOIN subjects s ON s.id=a.subject_id JOIN users u ON u.id=a.teacher_id WHERE a.class_id=? ORDER BY sc.lesson_no,sc.weekday',[g('class_id')]));
case 'schedule_save': need('admin','dyrektor','sekretariat');
  $w=(int)g('weekday');$n=(int)g('lesson_no');if($w<1||$w>5||$n<1||$n>10)err('Nieprawidłowy dzień lub numer lekcji');
  $a=one('SELECT class_id,teacher_id FROM assignments WHERE id=?',[g('assignment_id')]);if(!$a)err('Wybierz przydział');
  if(one('SELECT 1 FROM schedule sc JOIN assignments x ON x.id=sc.assignment_id WHERE sc.weekday=? AND sc.lesson_no=? AND (x.class_id=? OR x.teacher_id=?)',[$w,$n,$a['class_id'],$a['teacher_id']]))err('Ta godzina jest już zajęta (klasa lub nauczyciel)');
  q('INSERT INTO schedule(assignment_id,weekday,lesson_no) VALUES(?,?,?)',[g('assignment_id'),$w,$n]);out(['ok'=>1]);
case 'schedule_delete': need('admin','dyrektor','sekretariat');q('DELETE FROM schedule WHERE id=?',[g('id')]);out(['ok'=>1]);

// ---------- Dziennik: obecności i tematy (TEST-37) ----------
case 'my_assignments': need('nauczyciel','wychowawca');
  out(all('SELECT a.id,c.name class,s.name subject FROM assignments a JOIN classes c ON c.id=a.class_id JOIN subjects s ON s.id=a.subject_id WHERE a.teacher_id=? ORDER BY c.name,s.name',[$U['id']]));
case 'lesson_open': need('nauczyciel','wychowawca');
  $d=g('date');if(!validDate($d))err('Podaj poprawną datę');$no=(int)g('lesson_no',1);
  $a=one('SELECT a.*,s.name subject,c.name class FROM assignments a JOIN subjects s ON s.id=a.subject_id JOIN classes c ON c.id=a.class_id WHERE a.id=? AND a.teacher_id=?',[g('assignment_id'),$U['id']]);
  if(!$a){logit('DENIED','lesson_open '.g('assignment_id'));err('To nie jest Twoja lekcja',403);}
  $l=one('SELECT * FROM lessons WHERE assignment_id=? AND date=? AND lesson_no=?',[$a['id'],$d,$no]);
  $st=all('SELECT s.id,s.first,s.last,(SELECT status FROM attendance WHERE lesson_id=? AND student_id=s.id) status FROM students s WHERE class_id=? ORDER BY last,first',[$l['id']??0,$a['class_id']]);
  logit('lesson_open',"{$a['class']} $d");out(['assignment'=>$a,'topic'=>$l['topic']??'','students'=>$st]);
case 'lesson_save': need('nauczyciel','wychowawca');
  $d=g('date');if(!validDate($d))err('Podaj poprawną datę');$no=(int)g('lesson_no',1);$topic=trim(g('topic',''));
  if($topic==='')err('Wpisz temat lekcji');
  $a=one('SELECT * FROM assignments WHERE id=? AND teacher_id=?',[g('assignment_id'),$U['id']]);if(!$a){logit('DENIED','lesson_save');err('To nie jest Twoja lekcja',403);}
  q('INSERT INTO lessons(assignment_id,date,lesson_no,topic) VALUES(?,?,?,?) ON CONFLICT(assignment_id,date,lesson_no) DO UPDATE SET topic=excluded.topic',[$a['id'],$d,$no,$topic]);
  $lid=one('SELECT id FROM lessons WHERE assignment_id=? AND date=? AND lesson_no=?',[$a['id'],$d,$no])['id'];
  foreach((array)g('attendance',[]) as $sid=>$status){
    if(!in_array($status,ST))err('Nieprawidłowy status');
    $stu=one('SELECT * FROM students WHERE id=? AND class_id=?',[$sid,$a['class_id']]);if(!$stu)continue;
    $prev=one('SELECT status FROM attendance WHERE lesson_id=? AND student_id=?',[$lid,$sid])['status']??null;
    // zatwierdzone usprawiedliwienie obejmujące datę zamienia nieobecność na usprawiedliwioną
    if($status=='nieobecny'&&one("SELECT 1 FROM excuses WHERE student_id=? AND status='zatwierdzone' AND ? BETWEEN date_from AND date_to",[$sid,$d]))$status='usprawiedliwiony';
    q('INSERT INTO attendance VALUES(?,?,?) ON CONFLICT(lesson_id,student_id) DO UPDATE SET status=excluded.status',[$lid,$sid,$status]);
    if($status=='nieobecny'&&$prev!='nieobecny')foreach(all('SELECT parent_id FROM parent_links WHERE student_id=? AND restricted=0',[$sid]) as $p)
      notify($p['parent_id'],"Nieusprawiedliwiona nieobecność: {$stu['first']} {$stu['last']}, $d (lekcja $no).");}
  logit('lesson_save',"assignment {$a['id']} $d");out(['ok'=>1]);
case 'excuse_submit': need('rodzic');
  needStudent(g('student_id'));
  if(!validDate(g('from'))||!validDate(g('to'))||g('to')<g('from'))err('Podaj poprawny zakres dat');
  if(trim(g('reason',''))==='')err('Podaj powód nieobecności');
  q("INSERT INTO excuses(student_id,parent_id,date_from,date_to,reason,created) VALUES(?,?,?,?,?,datetime('now','localtime'))",[g('student_id'),$U['id'],g('from'),g('to'),trim(g('reason'))]);
  $s=one('SELECT s.first,s.last,c.homeroom_id FROM students s LEFT JOIN classes c ON c.id=s.class_id WHERE s.id=?',[g('student_id')]);
  if($s['homeroom_id'])notify($s['homeroom_id'],"Nowe usprawiedliwienie: {$s['first']} {$s['last']} (".g('from').' – '.g('to').')');
  logit('excuse_submit',(string)g('student_id'));out(['ok'=>1]);
case 'excuses_list': need('rodzic','wychowawca','admin','dyrektor','sekretariat');
  $rows=all('SELECT e.*,s.first||" "||s.last student,s.class_id FROM excuses e JOIN students s ON s.id=e.student_id ORDER BY e.id DESC');
  out(array_values(array_filter($rows,function($e)use($U){
    if($U['role']=='rodzic')return $e['parent_id']==$U['id'];
    if($U['role']=='wychowawca')return in_array($e['class_id'],homeIds());return true;})));
case 'excuse_decide': need('wychowawca','admin');
  $e=one('SELECT * FROM excuses WHERE id=?',[g('id')]);if(!$e)err('Nie znaleziono',404);
  if($U['role']=='wychowawca'&&!isHomeroomOf($e['student_id'])){logit('DENIED','excuse_decide');err('Możesz decydować tylko dla swojej klasy',403);}
  $d=g('decision');if(!in_array($d,['zatwierdzone','odrzucone']))err('Nieprawidłowa decyzja');
  q('UPDATE excuses SET status=? WHERE id=?',[$d,$e['id']]);
  if($d=='zatwierdzone')q("UPDATE attendance SET status='usprawiedliwiony' WHERE student_id=? AND status='nieobecny' AND lesson_id IN(SELECT id FROM lessons WHERE date BETWEEN ? AND ?)",[$e['student_id'],$e['date_from'],$e['date_to']]);
  notify($e['parent_id'],"Usprawiedliwienie ({$e['date_from']} – {$e['date_to']}): $d.");logit('excuse_decide',"{$e['id']} $d");out(['ok'=>1]);
case 'attendance_history': need();
  $sid=g('student_id');needStudent($sid);
  $rows=all('SELECT l.date,l.lesson_no,l.topic,s.name subject,a.status FROM attendance a JOIN lessons l ON l.id=a.lesson_id JOIN assignments x ON x.id=l.assignment_id JOIN subjects s ON s.id=x.subject_id WHERE a.student_id=? ORDER BY l.date DESC,l.lesson_no',[$sid]);
  logit('attendance_history',(string)$sid);out($rows);
case 'stats': need('admin','dyrektor','sekretariat','wychowawca');
  $w='';$p=[];if($U['role']=='wychowawca'){$w='WHERE s.class_id IN(SELECT id FROM classes WHERE homeroom_id=?)';$p=[$U['id']];}
  $rows=all("SELECT s.id,s.first,s.last,c.name class,COUNT(a.status) total,COALESCE(SUM(a.status='obecny'),0) ob,COALESCE(SUM(a.status='spóźniony'),0) sp,COALESCE(SUM(a.status='nieobecny'),0) nb,COALESCE(SUM(a.status='usprawiedliwiony'),0) us,COALESCE(SUM(a.status='zwolniony'),0) zw FROM students s LEFT JOIN classes c ON c.id=s.class_id LEFT JOIN attendance a ON a.student_id=s.id $w GROUP BY s.id ORDER BY c.name,s.last",$p);
  foreach($rows as &$r)$r['pct']=$r['total']?round(100*($r['ob']+$r['sp'])/$r['total'],1):null; // frekwencja = obecny + spóźniony
  out($rows);
case 'missing': need('wychowawca','dyrektor','admin');
  $w=$U['role']=='wychowawca'?' AND c.homeroom_id='.(int)$U['id']:'';
  $slots=all("SELECT sc.weekday,sc.lesson_no,a.id aid,s.name subject,c.name class,u.name teacher FROM schedule sc JOIN assignments a ON a.id=sc.assignment_id JOIN subjects s ON s.id=a.subject_id JOIN classes c ON c.id=a.class_id JOIN users u ON u.id=a.teacher_id WHERE 1=1 $w");
  $res=[];for($i=0;$i<7;$i++){$d=date('Y-m-d',strtotime("-$i day"));$wd=(int)date('N',strtotime($d));
    foreach($slots as $s)if($s['weekday']==$wd&&!one('SELECT 1 FROM lessons l WHERE l.assignment_id=? AND l.date=? AND l.lesson_no=? AND EXISTS(SELECT 1 FROM attendance WHERE lesson_id=l.id)',[$s['aid'],$d,$s['lesson_no']]))$res[]=$s+['date'=>$d];}
  out($res);
case 'notif_list': need();$r=all('SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 50',[$U['id']]);q('UPDATE notifications SET is_read=1 WHERE user_id=?',[$U['id']]);out($r);
default: err('Nieznana akcja',404);
}}catch(PDOException $e){err('Błąd bazy danych',500);}
