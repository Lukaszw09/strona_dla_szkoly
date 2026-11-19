<?php
// Schemat bazy danych
const SCHEMA = "
CREATE TABLE users(id INTEGER PRIMARY KEY,email TEXT UNIQUE NOT NULL,password TEXT NOT NULL,role TEXT NOT NULL,name TEXT NOT NULL,failed INTEGER DEFAULT 0,locked INTEGER DEFAULT 0);
CREATE TABLE classes(id INTEGER PRIMARY KEY,name TEXT UNIQUE NOT NULL,homeroom_id INTEGER);
CREATE TABLE students(id INTEGER PRIMARY KEY,user_id INTEGER,first TEXT NOT NULL,last TEXT NOT NULL,birth TEXT,pesel TEXT,address TEXT,class_id INTEGER,conduct TEXT DEFAULT 'dobre');
CREATE TABLE class_history(id INTEGER PRIMARY KEY,student_id INTEGER,class_id INTEGER,from_date TEXT);
CREATE TABLE subjects(id INTEGER PRIMARY KEY,name TEXT UNIQUE NOT NULL);
CREATE TABLE teacher_subjects(user_id INTEGER,subject_id INTEGER,PRIMARY KEY(user_id,subject_id));
CREATE TABLE parent_links(id INTEGER PRIMARY KEY,parent_id INTEGER,student_id INTEGER,relation TEXT,contact TEXT,pickup INTEGER DEFAULT 1,restricted INTEGER DEFAULT 0,UNIQUE(parent_id,student_id));
CREATE TABLE assignments(id INTEGER PRIMARY KEY,teacher_id INTEGER,class_id INTEGER,subject_id INTEGER,UNIQUE(teacher_id,class_id,subject_id));
CREATE TABLE schedule(id INTEGER PRIMARY KEY,assignment_id INTEGER,weekday INTEGER,lesson_no INTEGER,group_name TEXT NOT NULL DEFAULT '');
CREATE TABLE lessons(id INTEGER PRIMARY KEY,assignment_id INTEGER,date TEXT,lesson_no INTEGER,topic TEXT,UNIQUE(assignment_id,date,lesson_no));
CREATE TABLE attendance(lesson_id INTEGER,student_id INTEGER,status TEXT,PRIMARY KEY(lesson_id,student_id));
CREATE TABLE excuses(id INTEGER PRIMARY KEY,student_id INTEGER,parent_id INTEGER,date_from TEXT,date_to TEXT,reason TEXT,status TEXT DEFAULT 'oczekuje',created TEXT);
CREATE TABLE notifications(id INTEGER PRIMARY KEY,user_id INTEGER,text TEXT,created TEXT,is_read INTEGER DEFAULT 0);
CREATE TABLE access_log(id INTEGER PRIMARY KEY,user_id INTEGER,action TEXT,detail TEXT,ip TEXT,created TEXT);
CREATE TABLE reset_tokens(email TEXT,token TEXT,expires INTEGER);
CREATE TABLE grades(id INTEGER PRIMARY KEY,student_id INTEGER,subject_id INTEGER,teacher_id INTEGER,value INTEGER NOT NULL,kind TEXT NOT NULL DEFAULT 'cząstkowa',date TEXT);
CREATE TABLE remarks(id INTEGER PRIMARY KEY,student_id INTEGER,teacher_id INTEGER,text TEXT,date TEXT);
CREATE TABLE cms_sections(id INTEGER PRIMARY KEY,slug TEXT UNIQUE NOT NULL,title TEXT NOT NULL,content TEXT NOT NULL,section_order INTEGER DEFAULT 0,published INTEGER DEFAULT 1);
CREATE TABLE cms_menu(id INTEGER PRIMARY KEY,title TEXT NOT NULL,slug TEXT NOT NULL,menu_order INTEGER DEFAULT 0,published INTEGER DEFAULT 1);
CREATE TABLE announcements(id INTEGER PRIMARY KEY,title TEXT NOT NULL,content TEXT NOT NULL,published_at TEXT,published INTEGER DEFAULT 1);
CREATE TABLE events(id INTEGER PRIMARY KEY,title TEXT NOT NULL,event_date TEXT NOT NULL,event_time TEXT,end_date TEXT,description TEXT,location TEXT);
CREATE TABLE gallery(id INTEGER PRIMARY KEY,title TEXT NOT NULL,image_url TEXT NOT NULL,description TEXT,created_at TEXT);
CREATE TABLE documents(id INTEGER PRIMARY KEY,title TEXT NOT NULL,file_url TEXT NOT NULL,category TEXT,created_at TEXT);
CREATE TABLE meal_menus(id INTEGER PRIMARY KEY,menu_date TEXT NOT NULL,title TEXT,description TEXT);
CREATE TABLE newsletter_subscribers(id INTEGER PRIMARY KEY,email TEXT UNIQUE NOT NULL,consent INTEGER DEFAULT 1,created_at TEXT);
CREATE TABLE feedback(id INTEGER PRIMARY KEY,user_id INTEGER,name TEXT,email TEXT,subject TEXT,message TEXT,status TEXT DEFAULT 'nowe',created_at TEXT);
CREATE TABLE messages(id INTEGER PRIMARY KEY,sender_id INTEGER,recipient_id INTEGER,subject TEXT,body TEXT,is_read INTEGER DEFAULT 0,created_at TEXT);
CREATE TABLE privacy_consents(id INTEGER PRIMARY KEY,user_id INTEGER,purpose TEXT,consent INTEGER NOT NULL,consent_text TEXT,created_at TEXT);
CREATE TABLE migration_runs(id INTEGER PRIMARY KEY,name TEXT UNIQUE NOT NULL,status TEXT,details TEXT,run_at TEXT);
";
