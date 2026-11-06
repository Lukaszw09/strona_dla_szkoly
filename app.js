'use strict';
let ME=null,CUR='',H={},CACHE={},LESSON=null;
const $=s=>document.querySelector(s);
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const ST=['obecny','nieobecny','spóźniony','zwolniony','usprawiedliwiony'];
const DAYS=['Pn','Wt','Śr','Cz','Pt'];
const today=()=>new Date().toISOString().slice(0,10);
const badge=s=>`<span class="b s-${esc(s)}">${esc(s)}</span>`;
const opt=(a,v,l,sel)=>a.map(x=>`<option value="${esc(x[v])}"${x[v]==sel?' selected':''}>${esc(typeof l=='function'?l(x):x[l])}</option>`).join('');
const tbl=(h,r)=>`<div class="tw"><table><thead><tr>${h.map(x=>`<th>${x}</th>`).join('')}</tr></thead><tbody>${r.map(x=>`<tr${x.cls?` class="${x.cls}"`:''}>${x.map(c=>`<td>${c}</td>`).join('')}</tr>`).join('')||`<tr><td colspan="${h.length}" class="muted">Brak danych</td></tr>`}</tbody></table></div>`;
const btn=(c,a,t,k='')=>`<button class="sm ${k}" data-c="${c}" data-a="${esc(a)}">${t}</button>`;
const is=(...r)=>r.includes(ME.role);
let tt;function toast(m,bad){const t=$('#toast');t.textContent=m;t.className='show'+(bad?' bad':'');clearTimeout(tt);tt=setTimeout(()=>t.className='',3500)}
async function api(a,d){const r=await fetch('api.php?action='+a,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d||{})});
  let j;try{j=await r.json()}catch(e){j={error:'Błąd serwera'}}
  if(r.status===401&&a!=='login'&&a!=='me'){ME=null;showLogin();toast('Sesja wygasła – zaloguj się ponownie',1);throw j}
  if(j.error){toast(j.error,1);throw j}return j}
const fill=(f,o)=>{for(const k in o){const e=f.elements[k];if(!e)continue;if(e.type=='checkbox')e.checked=!!+o[k];else e.value=o[k]??''}f.scrollIntoView({block:'center'})};
const checked=(f,n)=>[...f.querySelectorAll(`[name=${n}]:checked`)].map(x=>x.value);

// ---------- widoki ----------
const ALL=['admin','dyrektor','sekretariat','nauczyciel','wychowawca','rodzic','uczen'];
const STAFF=['admin','dyrektor','sekretariat','nauczyciel','wychowawca'];
const V={
 lesson:['Lekcja i frekwencja',['nauczyciel','wychowawca'],vLesson],
 missing:['Braki we frekwencji',['wychowawca','dyrektor','admin'],vMissing],
 excuses:['Usprawiedliwienia',['rodzic','wychowawca','dyrektor','sekretariat','admin'],vExcuses],
 history:['Historia frekwencji',ALL,vHistory],
 stats:['Statystyki frekwencji',['admin','dyrektor','sekretariat','wychowawca'],vStats],
 students:['Uczniowie',STAFF,vStudents],
 classes:['Klasy',STAFF.slice(0,3).concat(['wychowawca','nauczyciel']),vClasses],
 teachers:['Nauczyciele',['admin','dyrektor','sekretariat'],vTeachers],
 parents:['Rodzice i opiekunowie',['admin','sekretariat','dyrektor'],vParents],
 subjects:['Przedmioty',['admin','dyrektor','sekretariat','nauczyciel','wychowawca'],vSubjects],
 assign:['Przydziały',['admin','dyrektor','sekretariat'],vAssign],
 schedule:['Plan lekcji',STAFF,vSchedule],
 users:['Użytkownicy i role',['admin'],vUsers],
 log:['Logi dostępu',['admin','dyrektor'],vLog],
 notif:['Powiadomienia',ALL,vNotif]};

async function vLesson(){const as=await api('my_assignments');
 return `<h2>Lekcja i frekwencja</h2><p class="muted">Widzisz tylko klasy i przedmioty przypisane do Ciebie.</p>
 <form class="row" data-h="openLesson"><label>Klasa i przedmiot<select name="assignment_id">${opt(as,'id',a=>a.class+' – '+a.subject)}</select></label>
 <label>Data<input type="date" name="date" value="${today()}" required></label><label>Nr lekcji<input type="number" name="lesson_no" min="1" max="10" value="1"></label><button>Otwórz lekcję</button></form><div id="lessonBox"></div>`}
H.openLesson=async d=>{const r=await api('lesson_open',d);LESSON=d;
 $('#lessonBox').innerHTML=`<form data-h="saveLesson"><h3>${esc(r.assignment.class)} – ${esc(r.assignment.subject)}, ${esc(d.date)}, lekcja ${esc(d.lesson_no)}</h3>
 <label>Temat lekcji<input name="topic" value="${esc(r.topic)}" required></label>
 ${tbl(['Uczeń','Status'],r.students.map(s=>[esc(s.last+' '+s.first),`<select name="st_${s.id}">${ST.map(x=>`<option${(s.status||'obecny')==x?' selected':''}>${x}</option>`).join('')}</select>`]))}
 <div><button>Zapisz temat i frekwencję</button> <button type="button" class="sec" data-c="allPresent">Wszyscy obecni</button></div></form>`};
H.allPresent=()=>document.querySelectorAll('[name^=st_]').forEach(s=>s.value='obecny');
H.saveLesson=async d=>{const att={};for(const k in d)if(k.startsWith('st_'))att[k.slice(3)]=d[k];
 await api('lesson_save',{...LESSON,topic:d.topic,attendance:att});toast('Zapisano temat i frekwencję')};

async function vMissing(){const r=await api('missing');
 return `<h2>Braki we frekwencji (ostatnie 7 dni)</h2><p class="muted">Lekcje z planu, na których nauczyciel nie uzupełnił frekwencji.</p>`+
 tbl(['Data','Lekcja','Klasa','Przedmiot','Nauczyciel'],r.map(x=>[x.date,x.lesson_no,esc(x.class),esc(x.subject),esc(x.teacher)]))}

async function vExcuses(){const e=await api('excuses_list');let form='';
 if(is('rodzic')){const kids=await api('students_list');form=`<h3>Zgłoś nieobecność dziecka</h3><form class="row" data-h="submitExcuse"><label>Dziecko<select name="student_id">${opt(kids,'id',k=>k.first+' '+k.last)}</select></label>
 <label>Od<input type="date" name="from" value="${today()}" required></label><label>Do<input type="date" name="to" value="${today()}" required></label>
 <label>Powód<input name="reason" placeholder="np. choroba" required></label><button>Wyślij zgłoszenie</button></form>`}
 return `<h2>Usprawiedliwienia</h2>${form}`+tbl(['Uczeń','Okres','Powód','Status',''],e.map(x=>[esc(x.student),`${x.date_from} – ${x.date_to}`,esc(x.reason),badge(x.status),
  x.status=='oczekuje'&&is('wychowawca','admin')?btn('decide',x.id+':zatwierdzone','Zatwierdź')+' '+btn('decide',x.id+':odrzucone','Odrzuć','bad'):'']))}
H.submitExcuse=async d=>{await api('excuse_submit',d);toast('Zgłoszenie wysłane do wychowawcy');go('excuses')};
H.decide=async a=>{const[i,d]=a.split(':');await api('excuse_decide',{id:i,decision:d});toast('Zapisano decyzję');go('excuses')};

async function vHistory(){const kids=await api('students_list');setTimeout(()=>kids[0]&&H.showHist({student_id:kids[0].id}),0);
 return `<h2>Historia frekwencji</h2><form class="row" data-h="showHist"><label>Uczeń<select name="student_id">${opt(kids,'id',k=>k.last+' '+k.first+(k.class?' ('+k.class+')':''))}</select></label><button>Pokaż</button></form><div id="hist"></div>`}
H.showHist=async d=>{const r=await api('attendance_history',{student_id:d.student_id});const m={};r.forEach(x=>(m[x.date.slice(0,7)]??=[]).push(x));
 $('#hist').innerHTML=Object.keys(m).sort().reverse().map(k=>{const rows=m[k],n=s=>rows.filter(x=>x.status==s).length;
  return `<h3 class="month">${new Date(k+'-01').toLocaleDateString('pl-PL',{month:'long',year:'numeric'})}</h3>
  <p class="muted">obecny: ${n('obecny')} · spóźniony: ${n('spóźniony')} · nieobecny: ${n('nieobecny')} · usprawiedliwiony: ${n('usprawiedliwiony')} · zwolniony: ${n('zwolniony')}</p>`+
  tbl(['Data','Lekcja','Przedmiot','Temat','Status'],rows.map(x=>[x.date,x.lesson_no,esc(x.subject),esc(x.topic),badge(x.status)]))}).join('')||'<p class="muted">Brak wpisów frekwencji.</p>'};

let STATS=[];
async function vStats(){STATS=await api('stats');setTimeout(()=>H.filterStats({th:75}),0);
 return `<h2>Statystyki frekwencji</h2><p class="muted">Frekwencja = (obecny + spóźniony) / wszystkie wpisy.</p>
 <form class="row" data-h="filterStats"><label>Pokaż uczniów z frekwencją poniżej (%)<input type="number" name="th" min="0" max="100" value="75"></label><button>Filtruj</button></form><div id="st"></div>`}
H.filterStats=d=>{const th=+d.th;const rows=STATS.filter(s=>s.pct!==null&&s.pct<th);
 $('#st').innerHTML=`<p><strong>${rows.length}</strong> uczniów poniżej progu ${th}%</p>`+tbl(['Uczeń','Klasa','Frekwencja','Obecny','Spóźniony','Nieobecny','Usprawiedl.','Zwolniony'],
  rows.map(s=>Object.assign([esc(s.last+' '+s.first),esc(s.class),s.pct+'%',s.ob,s.sp,s.nb,s.us,s.zw],{cls:'low'})))+
  `<h3>Wszyscy uczniowie</h3>`+tbl(['Uczeń','Klasa','Frekwencja'],STATS.map(s=>[esc(s.last+' '+s.first),esc(s.class),s.pct===null?'–':s.pct+'%']))};

async function vStudents(){const cl=await api('classes_list');CACHE.cl=cl;const ed=is('admin','sekretariat');setTimeout(()=>H.searchStu({}),0);
 let f='';if(ed)f=`<h3>Dodaj / edytuj ucznia</h3><form data-h="saveStu"><input type="hidden" name="id"><fieldset>
 <label>Imię<input name="first" required></label><label>Nazwisko<input name="last" required></label><label>Data urodzenia<input type="date" name="birth"></label>
 <label>PESEL<input name="pesel" maxlength="11" pattern="\\d{11}"></label><label>Adres<input name="address"></label><label>Klasa (tylko nowy uczeń)<select name="class_id"><option value="">–</option>${opt(cl,'id','name')}</select></label></fieldset>
 <div><button>Zapisz ucznia</button></div></form>
 <h3>Przenieś ucznia do innej klasy</h3><form class="row" data-h="moveStu"><label>Uczeń<select name="student_id" id="moveSel"></select></label><label>Nowa klasa<select name="class_id">${opt(cl,'id','name')}</select></label><label>Data zmiany<input type="date" name="date" value="${today()}" required></label><button>Przenieś</button></form>`;
 return `<h2>Uczniowie</h2><form class="row" data-h="searchStu"><label>Szukaj (nazwisko, klasa${ed?', PESEL':''})<input name="q"></label><label>Klasa<select name="class_id"><option value="">wszystkie</option>${opt(cl,'id','name')}</select></label><button>Szukaj</button></form><div id="stuList"></div>${f}`}
H.searchStu=async d=>{const r=await api('students_list',d);CACHE.stu=r;const ed=is('admin','sekretariat');
 $('#stuList').innerHTML=tbl(['Nazwisko i imię','Klasa',...(ed?['Data ur.','PESEL','Adres','']:[])],r.map(s=>[esc(s.last+' '+s.first),esc(s.class),...(ed?[esc(s.birth),esc(s.pesel),esc(s.address),btn('editStu',s.id,'Edytuj','sec')]:[])]));
 const m=$('#moveSel');if(m&&!d.q&&!d.class_id)m.innerHTML=opt(r,'id',s=>s.last+' '+s.first+' ('+(s.class||'-')+')')};
H.editStu=id=>fill($('form[data-h=saveStu]'),CACHE.stu.find(s=>s.id==id));
H.saveStu=async(d,f)=>{await api('student_save',d);toast('Zapisano ucznia');f.reset();go('students')};
H.moveStu=async d=>{await api('student_move',d);toast('Uczeń przeniesiony – historia zachowana');go('students')};

async function vClasses(){const c=await api('classes_list');const ed=is('admin','sekretariat');let f='';
 if(ed){const t=(await api('teachers_list')).filter(x=>x.role=='wychowawca');
  f=`<h3>Dodaj / edytuj klasę</h3><form class="row" data-h="saveClass"><input type="hidden" name="id"><label>Nazwa<input name="name" required></label><label>Wychowawca<select name="homeroom_id"><option value="">–</option>${opt(t,'id','name')}</select></label><button>Zapisz klasę</button></form>`;CACHE.c=c}
 return `<h2>Klasy</h2>`+tbl(['Klasa','Wychowawca','Liczba uczniów',...(ed?['']:[])],c.map(x=>[esc(x.name),esc(x.homeroom),x.n,...(ed?[btn('editClass',x.id,'Edytuj','sec')]:[])]))+f}
H.editClass=id=>fill($('form[data-h=saveClass]'),CACHE.c.find(x=>x.id==id));
H.saveClass=async d=>{await api('class_save',d);toast('Zapisano klasę');go('classes')};

async function vTeachers(){const[t,s]=await Promise.all([api('teachers_list'),api('subjects_list')]);const ed=is('admin','sekretariat');
 return `<h2>Nauczyciele</h2>`+tbl(['Imię i nazwisko','E-mail','Rola','Przedmioty'],t.map(x=>[esc(x.name),esc(x.email),esc(x.role),esc(x.subjects)]))+(ed?
 `<h3>Dodaj nauczyciela</h3><form data-h="saveTeacher"><fieldset><label>Imię i nazwisko<input name="name" required></label><label>E-mail<input name="email" type="email" required></label>
 <label>Hasło początkowe<input name="password" minlength="8" required></label><label>Rola<select name="role"><option>nauczyciel</option><option>wychowawca</option></select></label></fieldset>
 <fieldset><legend>Przedmioty</legend>${s.map(x=>`<label class="ck"><input type="checkbox" name="subjects" value="${x.id}">${esc(x.name)}</label>`).join('')}</fieldset><div><button>Dodaj nauczyciela</button></div></form>`:'')}
H.saveTeacher=async(d,f)=>{d.subjects=checked(f,'subjects');await api('user_save',d);toast('Dodano nauczyciela');go('teachers')};

async function vParents(){const[p,l,s]=await Promise.all([api('parents_list'),api('links_list'),api('students_list')]);
 return `<h2>Rodzice i opiekunowie</h2><h3>Powiązania z uczniami</h3>`+
 tbl(['Rodzic','Uczeń','Relacja','Kontakt','Odbiór','Ograniczenie',''],l.map(x=>[esc(x.parent),esc(x.student),esc(x.relation),esc(x.contact),x.pickup?'tak':'nie',x.restricted?'<span class="b s-nieobecny">zakaz dostępu</span>':'–',btn('delLink',x.id,'Usuń','bad')]))+
 `<h3>Powiąż rodzica z uczniem</h3><form data-h="saveLink"><fieldset><label>Rodzic<select name="parent_id">${opt(p,'id','name')}</select></label><label>Uczeń<select name="student_id">${opt(s,'id',x=>x.last+' '+x.first)}</select></label>
 <label>Relacja<input name="relation" placeholder="matka, ojciec, opiekun"></label><label>Kontakt<input name="contact"></label>
 <label class="ck"><input type="checkbox" name="pickup" checked>prawo do odbioru</label><label class="ck"><input type="checkbox" name="restricted">ograniczenie władzy rodzicielskiej (zakaz dostępu)</label></fieldset><div><button>Zapisz powiązanie</button></div></form>
 <h3>Dodaj konto rodzica</h3><form class="row" data-h="saveParent"><label>Imię i nazwisko<input name="name" required></label><label>E-mail<input name="email" type="email" required></label><label>Hasło początkowe<input name="password" minlength="8" required></label><button>Dodaj rodzica</button></form>`}
H.saveLink=async d=>{await api('link_save',d);toast('Zapisano powiązanie');go('parents')};
H.delLink=async id=>{if(confirm('Usunąć powiązanie?')){await api('link_delete',{id});go('parents')}};
H.saveParent=async d=>{await api('user_save',{...d,role:'rodzic'});toast('Dodano rodzica');go('parents')};

async function vSubjects(){const s=await api('subjects_list');
 return `<h2>Przedmioty</h2>`+tbl(['Przedmiot'],s.map(x=>[esc(x.name)]))+(is('admin')?`<form class="row" data-h="saveSubj"><label>Nowy przedmiot<input name="name" required></label><button>Dodaj</button></form>`:'')}
H.saveSubj=async d=>{await api('subject_save',d);toast('Dodano przedmiot');go('subjects')};

async function vAssign(){const[a,t,c,s]=await Promise.all([api('assignments_list'),api('teachers_list'),api('classes_list'),api('subjects_list')]);const ed=is('admin','dyrektor');
 return `<h2>Przydziały nauczyciel – klasa – przedmiot</h2>`+tbl(['Klasa','Przedmiot','Nauczyciel',...(ed?['']:[])],a.map(x=>[esc(x.class),esc(x.subject),esc(x.teacher),...(ed?[btn('delAssign',x.id,'Usuń','bad')]:[])]))+(ed?
 `<h3>Nowy przydział</h3><form class="row" data-h="saveAssign"><label>Nauczyciel<select name="teacher_id">${opt(t,'id','name')}</select></label><label>Klasa<select name="class_id">${opt(c,'id','name')}</select></label><label>Przedmiot<select name="subject_id">${opt(s,'id','name')}</select></label><button>Przypisz</button></form>`:'')}
H.saveAssign=async d=>{await api('assignment_save',d);toast('Dodano przydział');go('assign')};
H.delAssign=async id=>{if(confirm('Usunąć przydział wraz z jego pozycjami w planie?')){await api('assignment_delete',{id});go('assign')}};

async function vSchedule(){const c=await api('classes_list');CACHE.cl=c;setTimeout(()=>c[0]&&H.showPlan({class_id:c[0].id}),0);
 return `<h2>Plan lekcji</h2><form class="row" data-h="showPlan"><label>Klasa<select name="class_id">${opt(c,'id','name')}</select></label><button>Pokaż plan</button></form><div id="plan"></div>`}
H.showPlan=async d=>{CACHE.cid=d.class_id;const p=await api('schedule_list',{class_id:d.class_id});const ed=is('admin','dyrektor','sekretariat');let rows=[];
 for(let n=1;n<=8;n++)rows.push([n,...DAYS.map((_,i)=>p.filter(x=>x.lesson_no==n&&x.weekday==i+1).map(x=>`${esc(x.subject)}<br><span class="muted">${esc(x.teacher)}</span> ${ed?btn('delSlot',x.id,'×','bad'):''}`).join('')||'')]);
 let f='';if(ed){const a=(await api('assignments_list')).filter(x=>x.class_id==d.class_id);
  f=`<h3>Dodaj lekcję do planu</h3><form class="row" data-h="saveSlot"><label>Przedmiot<select name="assignment_id">${opt(a,'id',x=>x.subject+' – '+x.teacher)}</select></label><label>Dzień<select name="weekday">${DAYS.map((x,i)=>`<option value="${i+1}">${x}</option>`).join('')}</select></label><label>Nr lekcji<input type="number" name="lesson_no" min="1" max="10" value="1"></label><button>Dodaj do planu</button></form>`}
 $('#plan').innerHTML=tbl(['Lekcja',...DAYS],rows)+f};
H.saveSlot=async d=>{await api('schedule_save',d);toast('Dodano do planu');H.showPlan({class_id:CACHE.cid})};
H.delSlot=async id=>{await api('schedule_delete',{id});H.showPlan({class_id:CACHE.cid})};

async function vUsers(){const u=await api('users_list');CACHE.u=u;
 return `<h2>Użytkownicy i role</h2>`+tbl(['Imię i nazwisko','E-mail','Rola','Konto',''],u.map(x=>[esc(x.name),esc(x.email),esc(x.role),x.locked?'<span class="b s-nieobecny">zablokowane</span>':'aktywne',btn('editUser',x.id,'Edytuj','sec')]))+
 `<h3>Dodaj / edytuj użytkownika</h3><form data-h="saveUser"><input type="hidden" name="id"><fieldset><label>Imię i nazwisko<input name="name" required></label><label>E-mail<input name="email" type="email" required></label>
 <label>Rola<select name="role">${['admin','dyrektor','sekretariat','nauczyciel','wychowawca','rodzic','uczen'].map(r=>`<option>${r}</option>`).join('')}</select></label>
 <label>Hasło (puste = bez zmian)<input name="password" minlength="8"></label><label class="ck"><input type="checkbox" name="unlock">odblokuj konto</label></fieldset><div><button>Zapisz użytkownika</button></div></form>`}
H.editUser=id=>fill($('form[data-h=saveUser]'),CACHE.u.find(x=>x.id==id));
H.saveUser=async d=>{await api('user_save',d);toast('Zapisano użytkownika');go('users')};

async function vLog(){const l=await api('log_list');
 return `<h2>Logi logowań i dostępu do danych</h2>`+tbl(['Czas','Użytkownik','Zdarzenie','Szczegóły','IP'],l.map(x=>[esc(x.created),esc(x.email||'–'),esc(x.action),esc(x.detail),esc(x.ip)]))}
async function vNotif(){const n=await api('notif_list');ME.unread=0;head();
 return `<h2>Powiadomienia</h2>`+tbl(['Data','Treść'],n.map(x=>[esc(x.created),esc(x.text)]))}

// ---------- logowanie i nawigacja ----------
H.login=async d=>{const r=await api('login',d);start(r)};
H.logout=async()=>{await api('logout');ME=null;showLogin()};
H.showReset=()=>{$('#resetBox').hidden=!$('#resetBox').hidden};
H.resetReq=async d=>{await api('reset_request',d);toast('Jeśli konto istnieje, wysłano token resetu')};
H.resetDo=async d=>{await api('reset_confirm',d);toast('Hasło zmienione – możesz się zalogować')};
H.go=a=>go(a);
function head(){$('#who').textContent=`${ME.name} (${ME.role})${ME.unread?` · nowe powiadomienia: ${ME.unread}`:''}`}
function showLogin(){$('#app').hidden=true;$('#login').hidden=false}
function start(r){ME={...r.user,unread:r.unread};$('#login').hidden=true;$('#app').hidden=false;head();
 const keys=Object.keys(V).filter(k=>V[k][1].includes(ME.role));
 $('#nav').innerHTML=keys.map(k=>`<button data-c="go" data-a="${k}" id="n_${k}">${V[k][0]}</button>`).join('');go(keys[0])}
async function go(k){CUR=k;document.querySelectorAll('nav button').forEach(b=>b.classList.toggle('on',b.id=='n_'+k));
 try{$('#main').innerHTML=await V[k][2]()}catch(e){if(!e.error)console.error(e)}}
document.addEventListener('submit',async e=>{const h=e.target.dataset.h;if(!h)return;e.preventDefault();
 try{await H[h](Object.fromEntries(new FormData(e.target)),e.target)}catch(x){if(!x.error)console.error(x)}});
document.addEventListener('click',async e=>{const b=e.target.closest('[data-c]');if(!b)return;e.preventDefault();
 try{await H[b.dataset.c](b.dataset.a,b)}catch(x){if(!x.error)console.error(x)}});
api('me').then(r=>r.user?start(r):showLogin()).catch(showLogin);
