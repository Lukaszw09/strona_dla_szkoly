<?php
// Połączenie z SQLite + schemat + dane startowe (tworzone przy pierwszym uruchomieniu)
function db(){
  static $p; if($p) return $p;
  $dir=__DIR__.'/data'; if(!is_dir($dir)) mkdir($dir,0775,true);
  $f=$dir.'/dziennik.sqlite'; $new=!file_exists($f);
  $p=new PDO('sqlite:'.$f);
  $p->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
  $p->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
  if($new){ $p->exec(SCHEMA); seed($p); }
  return $p;
}
const SCHEMA="
CREATE TABLE users(id INTEGER PRIMARY KEY,email TEXT UNIQUE NOT NULL,password TEXT NOT NULL,role TEXT NOT NULL,name TEXT NOT NULL,failed INTEGER DEFAULT 0,locked INTEGER DEFAULT 0);
CREATE TABLE classes(id INTEGER PRIMARY KEY,name TEXT UNIQUE NOT NULL,homeroom_id INTEGER);
CREATE TABLE students(id INTEGER PRIMARY KEY,user_id INTEGER,first TEXT NOT NULL,last TEXT NOT NULL,birth TEXT,pesel TEXT,address TEXT,class_id INTEGER);
CREATE TABLE class_history(id INTEGER PRIMARY KEY,student_id INTEGER,class_id INTEGER,from_date TEXT);
CREATE TABLE subjects(id INTEGER PRIMARY KEY,name TEXT UNIQUE NOT NULL);
CREATE TABLE teacher_subjects(user_id INTEGER,subject_id INTEGER,PRIMARY KEY(user_id,subject_id));
CREATE TABLE parent_links(id INTEGER PRIMARY KEY,parent_id INTEGER,student_id INTEGER,relation TEXT,contact TEXT,pickup INTEGER DEFAULT 1,restricted INTEGER DEFAULT 0,UNIQUE(parent_id,student_id));
CREATE TABLE assignments(id INTEGER PRIMARY KEY,teacher_id INTEGER,class_id INTEGER,subject_id INTEGER,UNIQUE(teacher_id,class_id,subject_id));
CREATE TABLE schedule(id INTEGER PRIMARY KEY,assignment_id INTEGER,weekday INTEGER,lesson_no INTEGER);
CREATE TABLE lessons(id INTEGER PRIMARY KEY,assignment_id INTEGER,date TEXT,lesson_no INTEGER,topic TEXT,UNIQUE(assignment_id,date,lesson_no));
CREATE TABLE attendance(lesson_id INTEGER,student_id INTEGER,status TEXT,PRIMARY KEY(lesson_id,student_id));
CREATE TABLE excuses(id INTEGER PRIMARY KEY,student_id INTEGER,parent_id INTEGER,date_from TEXT,date_to TEXT,reason TEXT,status TEXT DEFAULT 'oczekuje',created TEXT);
CREATE TABLE notifications(id INTEGER PRIMARY KEY,user_id INTEGER,text TEXT,created TEXT,is_read INTEGER DEFAULT 0);
CREATE TABLE access_log(id INTEGER PRIMARY KEY,user_id INTEGER,action TEXT,detail TEXT,ip TEXT,created TEXT);
CREATE TABLE reset_tokens(email TEXT,token TEXT,expires INTEGER);
";
function seed($p){
  $h=password_hash('haslo123',PASSWORD_DEFAULT);
  $u=$p->prepare('INSERT INTO users(email,password,role,name) VALUES(?,?,?,?)');
  foreach([['admin','admin','Adam Administrator'],['dyrektor','dyrektor','Dorota Dyrektor'],['sekretariat','sekretariat','Sylwia Sekretarz'],
   ['nauczyciel','nauczyciel','Anna Nowak'],['wychowawca','wychowawca','Jan Kowalski'],['rodzic','rodzic','Piotr Wiśniewski'],['uczen','uczen','Maja Wiśniewska']] as $r)
    $u->execute([$r[0].'@szkola.local',$h,$r[1],$r[2]]);
  $p->exec("INSERT INTO classes(name,homeroom_id) VALUES('4A',5),('4B',NULL)");
  $s=$p->prepare('INSERT INTO students(user_id,first,last,birth,pesel,address,class_id) VALUES(?,?,?,?,?,?,?)');
  $st=[[7,'Maja','Wiśniewska',1],[null,'Kacper','Zieliński',1],[null,'Zuzanna','Lewandowska',1],[null,'Antoni','Kamiński',1],[null,'Julia','Szymańska',1],[null,'Szymon','Dąbrowski',1],[null,'Hanna','Woźniak',2],[null,'Filip','Mazur',2],[null,'Lena','Krawczyk',2]];
  foreach($st as $i=>$r){ $s->execute([$r[0],$r[1],$r[2],'2016-0'.(1+$i%9).'-1'.($i%9),'1600'.sprintf('%07d',1234567+$i*111),'ul. Szkolna '.($i+1).', Katowice',$r[3]]);
    $p->prepare("INSERT INTO class_history(student_id,class_id,from_date) VALUES(?,?,'2025-09-01')")->execute([$i+1,$r[3]]); }
  $p->exec("INSERT INTO subjects(name) VALUES('Edukacja wczesnoszkolna'),('Matematyka'),('Język polski'),('Przyroda'),('Wychowanie fizyczne')");
  $p->exec("INSERT INTO teacher_subjects VALUES(4,2),(4,4),(5,1),(5,3)");
  $p->exec("INSERT INTO parent_links(parent_id,student_id,relation,contact,pickup,restricted) VALUES(6,1,'ojciec','600100200',1,0)");
  $p->exec("INSERT INTO assignments(teacher_id,class_id,subject_id) VALUES(5,1,1),(5,1,3),(4,1,2),(4,1,4),(4,2,2)");
  $p->exec("INSERT INTO schedule(assignment_id,weekday,lesson_no) VALUES(1,1,1),(1,2,1),(1,3,1),(2,1,2),(2,3,2),(3,1,3),(3,4,2),(4,2,3),(5,2,2),(5,4,1)");
  // dane demo frekwencji (ostatnie ~3 tygodnie, Matematyka 4A)
  $ss=['obecny','obecny','obecny','obecny','spóźniony','nieobecny','usprawiedliwiony','zwolniony'];
  for($d=21;$d>=1;$d--){ $t=strtotime("-$d day"); if(date('N',$t)>5) continue; $date=date('Y-m-d',$t);
    $p->prepare('INSERT INTO lessons(assignment_id,date,lesson_no,topic) VALUES(3,?,3,?)')->execute([$date,'Temat demo: działania na ułamkach ('.$date.')']);
    $lid=$p->lastInsertId();
    for($sid=1;$sid<=6;$sid++) $p->prepare('INSERT INTO attendance VALUES(?,?,?)')->execute([$lid,$sid,$ss[($sid*sid_mix($sid)+$d*3)%8]]); }
}
function sid_mix($s){ return $s%3+1; }
