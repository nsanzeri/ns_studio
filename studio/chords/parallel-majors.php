<?php
/**
 * Ready Set Shows - Parallel Majors Generator MVP
 * Drop-in path: /studio/chords/parallel-majors.php
 * Self-contained: no DB, no auth, no shared includes required.
 */
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Parallel Majors Generator | Ready Set Shows</title>
  <style>
    :root{--bg:#090b12;--panel:#111827;--text:#f8fafc;--muted:#aeb8cc;--line:rgba(255,255,255,.13);--accent:#fbbf24;--accent2:#fb7185;--blue:#60a5fa;--green:#86efac;--purple:#c084fc}
    *{box-sizing:border-box}body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:radial-gradient(circle at top left,#3b2a12 0,#111827 42%,#06070b 100%);color:var(--text);line-height:1.45}.wrap{max-width:1180px;margin:0 auto;padding:28px 18px 56px}.hero{display:grid;grid-template-columns:1.05fr .95fr;gap:18px;margin-bottom:18px}.card{background:linear-gradient(180deg,rgba(255,255,255,.075),rgba(255,255,255,.032));border:1px solid var(--line);border-radius:22px;box-shadow:0 18px 50px rgba(0,0,0,.32);padding:22px}.eyebrow{text-transform:uppercase;letter-spacing:.14em;font-size:12px;color:var(--accent);font-weight:900}h1{font-size:clamp(34px,5vw,58px);line-height:.98;margin:10px 0 14px}h2{font-size:22px;margin:0 0 14px}p{color:var(--muted);margin:0 0 12px}.grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px}.field{display:flex;flex-direction:column;gap:7px}label{font-size:13px;color:#e5e7eb;font-weight:800}select,input{width:100%;background:#0b1020;color:var(--text);border:1px solid var(--line);border-radius:14px;padding:11px 12px;font:inherit}button{border:0;border-radius:16px;padding:12px 16px;font-weight:900;cursor:pointer;color:#170f02;background:linear-gradient(135deg,var(--accent),var(--accent2));box-shadow:0 10px 28px rgba(251,191,36,.22)}button.secondary{background:#182236;color:var(--text);border:1px solid var(--line);box-shadow:none}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}button:disabled{opacity:.45;cursor:not-allowed}.progression{margin-top:18px;display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.measure{min-height:132px;background:#0b1020;border:1px solid var(--line);border-radius:18px;padding:14px;display:flex;flex-direction:column;justify-content:space-between}.measure strong{font-size:29px;letter-spacing:-.02em}.measure span{color:var(--muted);font-size:13px}.sourceTag{display:inline-block;border-radius:999px;padding:5px 8px;font-size:12px;background:rgba(251,191,36,.12);border:1px solid rgba(251,191,36,.26);color:#fff4cf}.home{border-color:rgba(251,191,36,.65);background:rgba(251,191,36,.11)}.borrowed-b3{border-color:rgba(96,165,250,.48);background:rgba(96,165,250,.09)}.borrowed-b6{border-color:rgba(134,239,172,.48);background:rgba(134,239,172,.08)}.borrowed-b7{border-color:rgba(192,132,252,.48);background:rgba(192,132,252,.09)}.pillrow{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}.pill{font-size:12px;color:#e5e7eb;background:rgba(251,191,36,.12);border:1px solid rgba(251,191,36,.25);border-radius:999px;padding:7px 10px}.note{margin-top:14px;background:rgba(251,191,36,.08);border:1px solid rgba(251,191,36,.22);border-radius:16px;padding:13px;color:#fff4cf}.small{font-size:13px;color:var(--muted)}.footer{margin-top:18px;color:var(--muted);font-size:13px}@media(max-width:960px){.hero{grid-template-columns:1fr}.grid{grid-template-columns:repeat(2,1fr)}.progression{grid-template-columns:repeat(2,1fr)}}@media(max-width:560px){.grid,.progression{grid-template-columns:1fr}.actions button{width:100%}}
  </style>
</head>
<body>
<main class="wrap">
  <section class="hero">
    <div class="card">
      <div class="eyebrow">Ready Set Shows Lab</div>
      <h1>Parallel Majors Generator</h1>
      <p>Keep the I chord from the selected key, then borrow the other chords from major scales built on the bIII, bVI, or bVII of the parallel minor.</p>
      <div class="pillrow"><div class="pill">I chord stays home</div><div class="pill">bIII Major</div><div class="pill">bVI Major</div><div class="pill">bVII Major</div><div class="pill">Mix source majors</div><div class="pill">MIDI download</div></div>
    </div>
    <div class="card">
      <h2>How this one thinks</h2>
      <p>Example in C: the I chord remains <strong>Cmaj7</strong>. The borrowed chords can come from <strong>Eb Major</strong>, <strong>Ab Major</strong>, or <strong>Bb Major</strong> — because Eb, Ab, and Bb are the 3rd, 6th, and 7th degrees of C minor.</p>
      <p class="small">This creates that familiar modal-mixture songwriter sound: home base stays clear, but the surrounding chords feel bigger, moodier, and less predictable.</p>
    </div>
  </section>

  <section class="card">
    <h2>Generator Controls</h2>
    <div class="grid">
      <div class="field"><label for="key">Key</label><select id="key"><option>C</option><option>Db</option><option>D</option><option>Eb</option><option>E</option><option>F</option><option>Gb</option><option>G</option><option>Ab</option><option>A</option><option>Bb</option><option>B</option></select></div>
      <div class="field"><label for="homeQuality">I Chord</label><select id="homeQuality"><option value="maj7" selected>maj7</option><option value="6">6</option><option value="maj9">maj9</option><option value="">major triad</option></select></div>
      <div class="field"><label for="sourceMajor">Borrow From</label><select id="sourceMajor"><option value="b3" selected>bIII Major</option><option value="b6">bVI Major</option><option value="b7">bVII Major</option></select></div>
      <div class="field"><label for="mixMajors">Mix Parallel Majors</label><select id="mixMajors"><option value="no" selected>No - selected source only</option><option value="yes">Yes - mix bIII, bVI, bVII</option></select></div>
      <div class="field"><label for="bars">Bars</label><select id="bars"><option>4</option><option selected>8</option><option>12</option><option>16</option></select></div>
      <div class="field"><label for="tempo">Tempo</label><input id="tempo" type="number" min="50" max="210" value="100"></div>
      <div class="field"><label for="homeFreq">Home Chord Frequency</label><select id="homeFreq"><option value="light">Light</option><option value="medium" selected>Medium</option><option value="heavy">Heavy</option></select></div>
      <div class="field"><label for="color">Chord Color</label><select id="color"><option value="triads">Triads</option><option value="sevenths" selected>7ths</option><option value="ninths">9ths sometimes</option></select></div>
      <div class="field"><label for="ending">Ending Bias</label><select id="ending"><option value="open" selected>Open ending</option><option value="home">Prefer home</option><option value="surprise">Surprise me</option></select></div>
    </div>
    <div class="actions">
      <button id="generateBtn">Generate Parallel Majors</button>
      <button id="playBtn" class="secondary" disabled>Play Preview</button>
      <button id="stopBtn" class="secondary" disabled>Stop</button>
      <button id="downloadBtn" class="secondary" disabled>Download MIDI</button>
    </div>
    <div id="result"></div>
  </section>
  <div class="footer">First pass: rule-based, self-contained, and intentionally easy to tune.</div>
</main>
<script>
const PC_TO_NAME_FLAT=['C','Db','D','Eb','E','F','Gb','G','Ab','A','Bb','B'];
const NOTE_TO_PC={C:0,Db:1,D:2,Eb:3,E:4,F:5,Gb:6,G:7,Ab:8,A:9,Bb:10,B:11};
const MAJOR=[0,2,4,5,7,9,11];
const SOURCE_OFFSETS={b3:3,b6:8,b7:10};
const SOURCE_LABELS={b3:'bIII Major',b6:'bVI Major',b7:'bVII Major'};
const ROMAN=['I','ii','iii','IV','V','vi','vii°'];
let current=null,audioCtx=null,playingNodes=[];
function pcName(pc){return PC_TO_NAME_FLAT[((pc%12)+12)%12];}
function choice(a){return a[Math.floor(Math.random()*a.length)]}
function chance(p){return Math.random()<p}
function homeProb(v){return v==='heavy'?.36:v==='medium'?.22:.11}
function sourceRoot(rootPc,source){return (rootPc+SOURCE_OFFSETS[source])%12}
function sourceName(rootPc,source){return pcName(sourceRoot(rootPc,source))+' Major'}
function majorDegreePc(srcRootPc,degree){return (srcRootPc+MAJOR[degree-1])%12}
function majorQuality(degree,color){
  const triad={1:'',2:'m',3:'m',4:'',5:'',6:'m',7:'dim'}[degree];
  if(color==='triads') return triad;
  if(degree===1 || degree===4) return color==='ninths'&&chance(.35)?'maj9':'maj7';
  if(degree===5) return color==='ninths'&&chance(.35)?'9':'7';
  if(degree===2 || degree===3 || degree===6) return color==='ninths'&&chance(.25)?'m9':'m7';
  return 'dim7';
}
function makeHome(rootPc,quality){return {chord:pcName(rootPc)+quality, pc:rootPc, quality:quality||'', roman:'I', source:'Home key', sourceKey: 'home', kind:'home'};}
function makeBorrowed(rootPc,source,degree,color){
  const srcRoot=sourceRoot(rootPc,source), pc=majorDegreePc(srcRoot,degree), q=majorQuality(degree,color);
  return {chord:pcName(pc)+q, pc, quality:q, roman:ROMAN[degree-1]+' / '+sourceName(rootPc,source), source:sourceName(rootPc,source), sourceKey:source, degree, kind:'borrowed'};
}
function chooseDegree(prevDegree){
  const pool=[1,2,3,4,5,6]; // avoid vii° by default
  let d=choice(pool);
  if(prevDegree && chance(.45)){
    const near=[prevDegree+1,prevDegree-1,4,5,6].filter(x=>pool.includes(x));
    d=choice(near);
  }
  return d;
}
function getSources(selected,mix){return mix==='yes'?['b3','b6','b7']:[selected];}
function generate(){
  const key=document.getElementById('key').value, rootPc=NOTE_TO_PC[key];
  const bars=parseInt(document.getElementById('bars').value,10), homeQuality=document.getElementById('homeQuality').value;
  const selected=document.getElementById('sourceMajor').value, mix=document.getElementById('mixMajors').value;
  const color=document.getElementById('color').value, ending=document.getElementById('ending').value;
  const hp=homeProb(document.getElementById('homeFreq').value), sources=getSources(selected,mix);
  const measures=[]; let prevDegree=null;
  for(let i=0;i<bars;i++){
    const isFirst=i===0, isLast=i===bars-1;
    let useHome=false;
    if(isFirst) useHome=true;
    else if(isLast && ending==='home') useHome=chance(.70);
    else if(isLast && ending==='surprise') useHome=chance(.08);
    else useHome=chance(hp);
    if(useHome){measures.push(makeHome(rootPc,homeQuality)); prevDegree=1; continue;}
    const source=choice(sources);
    let degree=chooseDegree(prevDegree);
    // For the last bar, favor musically useful non-home colors unless home is explicitly selected.
    if(isLast && ending==='open') degree=choice([2,4,5,6]);
    if(isLast && ending==='surprise') degree=choice([3,4,5,6,2]);
    measures.push(makeBorrowed(rootPc,source,degree,color));
    prevDegree=degree;
  }
  // Avoid too many exact repeated chords in a row.
  for(let i=1;i<measures.length;i++){
    if(measures[i].chord===measures[i-1].chord && measures[i].kind!=='home'){
      const source=choice(sources), degree=choice([2,3,4,5,6].filter(d=>d!==measures[i].degree));
      measures[i]=makeBorrowed(rootPc,source,degree,color);
    }
  }
  current={key,rootPc,bars,tempo:parseInt(document.getElementById('tempo').value,10)||100,measures};
  render();
  document.getElementById('playBtn').disabled=false;document.getElementById('downloadBtn').disabled=false;
}
function render(){
  const el=document.getElementById('result');
  let html='<div class="note"><strong>'+current.key+' Parallel Majors:</strong> Home I stays in '+current.key+'. Borrowed source choices: '+[...new Set(current.measures.filter(m=>m.kind==='borrowed').map(m=>m.source))].join(', ')+'.</div>';
  html+='<div class="progression">';
  current.measures.forEach((m,i)=>{
    const cls=m.kind==='home'?'home':'borrowed-'+m.sourceKey;
    html+='<div class="measure '+cls+'"><div><span>Measure '+(i+1)+' • '+(m.kind==='home'?'Home I':'Borrowed')+'</span><br><strong>'+m.chord+'</strong></div><div><span>'+m.roman+'</span><br><span class="sourceTag">'+m.source+'</span></div></div>';
  });
  html+='</div>';
  el.innerHTML=html;
}
function chordNotes(m){
  const intervals={
    '':[0,4,7], '6':[0,4,7,9], 'maj7':[0,4,7,11], 'maj9':[0,4,7,11,14],
    'm':[0,3,7], 'm7':[0,3,7,10], 'm9':[0,3,7,10,14],
    '7':[0,4,7,10], '9':[0,4,7,10,14], 'dim':[0,3,6], 'dim7':[0,3,6,9]
  };
  const ints=intervals[m.quality]||intervals[''];
  return ints.map(x=>60+((m.pc+x+12)%12)+(x>=12?12:0));
}
function stop(){playingNodes.forEach(n=>{try{n.stop()}catch(e){}});playingNodes=[];document.getElementById('stopBtn').disabled=true;}
function play(){
  stop(); audioCtx=new (window.AudioContext||window.webkitAudioContext)();
  const beat=60/current.tempo, bar=beat*4, now=audioCtx.currentTime+.05;
  current.measures.forEach((m,i)=>{
    const notes=chordNotes(m); const t=now+i*bar;
    notes.forEach((midi,idx)=>{
      const osc=audioCtx.createOscillator(), gain=audioCtx.createGain();
      osc.type='triangle'; osc.frequency.value=440*Math.pow(2,(midi-69)/12);
      gain.gain.setValueAtTime(0,t); gain.gain.linearRampToValueAtTime(.075,t+.025); gain.gain.exponentialRampToValueAtTime(.001,t+bar*.92);
      osc.connect(gain).connect(audioCtx.destination); osc.start(t+idx*.012); osc.stop(t+bar*.95); playingNodes.push(osc);
    });
    // soft bass root
    const bass=audioCtx.createOscillator(), bg=audioCtx.createGain(); bass.type='sine'; bass.frequency.value=440*Math.pow(2,((36+m.pc)-69)/12);
    bg.gain.setValueAtTime(0,t); bg.gain.linearRampToValueAtTime(.12,t+.02); bg.gain.exponentialRampToValueAtTime(.001,t+bar*.9);
    bass.connect(bg).connect(audioCtx.destination); bass.start(t); bass.stop(t+bar*.92); playingNodes.push(bass);
  });
  document.getElementById('stopBtn').disabled=false;
}
function varLen(v){let bytes=[];let buffer=v&0x7F;while(v>>=7){buffer<<=8;buffer|=((v&0x7F)|0x80)}while(true){bytes.push(buffer&0xFF);if(buffer&0x80)buffer>>=8;else break}return bytes}
function midiFile(){
  const tpq=480, tempo=Math.round(60000000/current.tempo), events=[];
  function ev(delta,arr){events.push(...varLen(delta),...arr)}
  ev(0,[0xFF,0x51,0x03,(tempo>>16)&255,(tempo>>8)&255,tempo&255]); ev(0,[0xC0,0x00]);
  current.measures.forEach(m=>{
    const notes=[36+m.pc,...chordNotes(m)];
    notes.forEach(n=>ev(0,[0x90,n,72]));
    notes.forEach((n,idx)=>ev(idx===0?tpq*4:0,[0x80,n,0]));
  });
  ev(0,[0xFF,0x2F,0x00]);
  const trackLen=events.length;
  const header=[0x4D,0x54,0x68,0x64,0,0,0,6,0,0,0,1,(tpq>>8)&255,tpq&255];
  const track=[0x4D,0x54,0x72,0x6B,(trackLen>>24)&255,(trackLen>>16)&255,(trackLen>>8)&255,trackLen&255,...events];
  return new Blob([new Uint8Array([...header,...track])],{type:'audio/midi'});
}
function downloadMidi(){
  const a=document.createElement('a'); a.href=URL.createObjectURL(midiFile()); a.download='parallel-majors-'+current.key+'.mid'; document.body.appendChild(a); a.click(); a.remove(); setTimeout(()=>URL.revokeObjectURL(a.href),1000);
}
document.getElementById('generateBtn').addEventListener('click',generate);
document.getElementById('playBtn').addEventListener('click',play);
document.getElementById('stopBtn').addEventListener('click',stop);
document.getElementById('downloadBtn').addEventListener('click',downloadMidi);
generate();
</script>
</body>
</html>
