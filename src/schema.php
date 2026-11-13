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
CREATE TABLE schedule(id INTEGER PRIMARY KEY,assignment_id INTEGER,weekday INTEGER,lesson_no INTEGER);
CREATE TABLE lessons(id INTEGER PRIMARY KEY,assignment_id INTEGER,date TEXT,lesson_no INTEGER,topic TEXT,UNIQUE(assignment_id,date,lesson_no));
CREATE TABLE attendance(lesson_id INTEGER,student_id INTEGER,status TEXT,PRIMARY KEY(lesson_id,student_id));
CREATE TABLE excuses(id INTEGER PRIMARY KEY,student_id INTEGER,parent_id INTEGER,date_from TEXT,date_to TEXT,reason TEXT,status TEXT DEFAULT 'oczekuje',created TEXT);
CREATE TABLE notifications(id INTEGER PRIMARY KEY,user_id INTEGER,text TEXT,created TEXT,is_read INTEGER DEFAULT 0);
CREATE TABLE access_log(id INTEGER PRIMARY KEY,user_id INTEGER,action TEXT,detail TEXT,ip TEXT,created TEXT);
CREATE TABLE reset_tokens(email TEXT,token TEXT,expires INTEGER);
CREATE TABLE grades(id INTEGER PRIMARY KEY,student_id INTEGER,subject_id INTEGER,teacher_id INTEGER,value INTEGER NOT NULL,kind TEXT NOT NULL DEFAULT 'cząstkowa',date TEXT);
CREATE TABLE remarks(id INTEGER PRIMARY KEY,student_id INTEGER,teacher_id INTEGER,text TEXT,date TEXT);
";
