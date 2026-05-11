<?php
/**
 * Ready Set Shows - Chord Generator MVP
 * Drop-in path: /studio/chords/index.php
 * Self-contained first pass: no DB, no auth, no shared includes required.
 */
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Chord Idea Machine | Ready Set Shows</title>
  <style>
    :root{--bg:#0b1020;--panel:#111936;--panel2:#172143;--text:#f6f7fb;--muted:#aeb7d1;--accent:#7dd3fc;--accent2:#c084fc;--line:rgba(255,255,255,.12);--good:#86efac;--warn:#fde68a;}
    *{box-sizing:border-box} body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:radial-gradient(circle at top left,#1c2b5b 0,#0b1020 38%,#070a14 100%);color:var(--text);line-height:1.45;}
    a{color:var(--accent)} .wrap{max-width:1120px;margin:0 auto;padding:28px 18px 52px}.hero{display:grid;grid-template-columns:1.15fr .85fr;gap:22px;align-items:stretch;margin-bottom:22px}.card{background:linear-gradient(180deg,rgba(255,255,255,.075),rgba(255,255,255,.035));border:1px solid var(--line);border-radius:22px;box-shadow:0 18px 50px rgba(0,0,0,.28);padding:22px}.eyebrow{text-transform:uppercase;letter-spacing:.14em;font-size:12px;color:var(--accent);font-weight:800}h1{font-size:clamp(34px,5vw,62px);line-height:.98;margin:10px 0 14px}h2{font-size:22px;margin:0 0 14px}p{color:var(--muted);margin:0 0 14px}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}.field{display:flex;flex-direction:column;gap:7px}label{font-size:13px;color:#dbe4ff;font-weight:700}select,input[type="number"]{width:100%;background:#0d1430;color:var(--text);border:1px solid var(--line);border-radius:14px;padding:11px 12px;font:inherit}button{border:0;border-radius:16px;padding:12px 16px;font-weight:800;cursor:pointer;color:#07101f;background:linear-gradient(135deg,var(--accent),var(--accent2));box-shadow:0 10px 30px rgba(125,211,252,.2)}button.secondary{background:#172143;color:var(--text);border:1px solid var(--line);box-shadow:none}button:disabled{opacity:.45;cursor:not-allowed}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}.result{margin-top:18px;display:grid;gap:12px}.progression{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.chord{min-height:92px;background:#0d1430;border:1px solid var(--line);border-radius:18px;padding:14px;display:flex;flex-direction:column;justify-content:space-between}.chord strong{font-size:28px}.chord span{color:var(--muted);font-size:13px}.pillrow{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}.pill{font-size:12px;color:#dbe4ff;background:rgba(125,211,252,.12);border:1px solid rgba(125,211,252,.24);border-radius:999px;padding:7px 10px}.note{background:rgba(253,230,138,.09);border:1px solid rgba(253,230,138,.22);border-radius:16px;padding:13px;color:#fef3c7}.small{font-size:13px;color:var(--muted)}.footer{margin-top:18px;color:var(--muted);font-size:13px}@media(max-width:900px){.hero{grid-template-columns:1fr}.grid{grid-template-columns:repeat(2,1fr)}.progression{grid-template-columns:repeat(2,1fr)}}@media(max-width:560px){.grid,.progression{grid-template-columns:1fr}.actions button{width:100%}}
  </style>
</head>
<body>
  <main class="wrap">
    <section class="hero">
      <div class="card">
        <div class="eyebrow">Ready Set Shows Lab</div>
        <h1>Chord Idea Machine</h1>
        <p>Generate a playable chord progression, hear it in the browser, and download it as a MIDI file for your DAW.</p>
        <div class="pillrow">
          <div class="pill">Guided random, not chaos</div><div class="pill">MIDI download</div><div class="pill">Writer-friendly starting points</div>
        </div>
      </div>
      <div class="card">
        <h2>First-pass MVP</h2>
        <p>This is intentionally self-contained. No database, no payment gating, and no shared includes yet. It is ready to live at <strong>/studio/chords/</strong>.</p>
        <div class="note small">Next passes can add saved progressions, user accounts, presets, Stripe gating, AI re-harmonization, bass lines, and Nashville/Roman numeral export.</div>
      </div>
    </section>

    <section class="card">
      <h2>Generator Controls</h2>
      <div class="grid">
        <div class="field"><label for="key">Key</label><select id="key"><option>C</option><option>Db</option><option>D</option><option>Eb</option><option>E</option><option>F</option><option>Gb</option><option>G</option><option>Ab</option><option>A</option><option>Bb</option><option>B</option></select></div>
        <div class="field"><label for="mode">Mode</label><select id="mode"><option value="major">Major</option><option value="minor">Minor</option></select></div>
        <div class="field"><label for="style">Style / Vibe</label><select id="style"><option value="pop">Pop / Singer-Songwriter</option><option value="country">Country</option><option value="rnb">R&B / Soul</option><option value="rock">Rock</option><option value="cinematic">Cinematic</option><option value="gospel">Gospel-ish</option></select></div>
        <div class="field"><label for="complexity">Complexity</label><select id="complexity"><option value="triads">Triads</option><option value="sevenths">7ths</option><option value="color">Color chords</option></select></div>
        <div class="field"><label for="bars">Bars</label><select id="bars"><option>4</option><option selected>8</option><option>12</option><option>16</option></select></div>
        <div class="field"><label for="tempo">Tempo</label><input id="tempo" type="number" min="50" max="190" value="92"></div>
        <div class="field"><label for="pattern">Rhythm</label><select id="pattern"><option value="whole">Whole notes</option><option value="push">Light push</option><option value="pulse">Quarter pulse</option></select></div>
        <div class="field"><label for="voicing">Voicing</label><select id="voicing"><option value="close">Close</option><option value="open">Open</option></select></div>
      </div>
      <div class="actions">
        <button id="generateBtn">Generate Progression</button>
        <button id="playBtn" class="secondary" disabled>Play Preview</button>
        <button id="stopBtn" class="secondary" disabled>Stop</button>
        <button id="downloadBtn" class="secondary" disabled>Download MIDI</button>
      </div>
      <div id="result" class="result"></div>
    </section>
    <div class="footer">Tip: generate several times, then steal the one that makes you want to write a melody.</div>
  </main>
<script>
const NOTE_TO_PC = {C:0,Db:1,D:2,Eb:3,E:4,F:5,Gb:6,G:7,Ab:8,A:9,Bb:10,B:11};
const PC_TO_SHARP = ['C','C#','D','D#','E','F','F#','G','G#','A','A#','B'];
const MAJOR_DEGREES = [0,2,4,5,7,9,11];
const MINOR_DEGREES = [0,2,3,5,7,8,10];
const DEGREE_LABELS = ['I','ii','iii','IV','V','vi','vii°'];
const MINOR_LABELS = ['i','ii°','III','iv','v','VI','VII'];
let current = null; let audioCtx = null; let playingNodes = [];
function choice(arr){ return arr[Math.floor(Math.random()*arr.length)]; }
function weighted(items){ let sum=items.reduce((a,b)=>a+b.w,0), r=Math.random()*sum; for(const it of items){ r-=it.w; if(r<=0) return it.v; } return items[0].v; }
const templates = {
  pop:{major:[[1,5,6,4],[6,4,1,5],[1,6,4,5],[4,1,5,6]], minor:[[1,6,3,7],[1,4,6,5],[6,7,1,1],[1,7,6,5]]},
  country:{major:[[1,4,5,1],[1,5,4,1],[1,6,4,5],[1,4,1,5]], minor:[[1,6,7,1],[1,4,5,1],[1,3,6,7]]},
  rnb:{major:[[2,5,1,6],[6,2,5,1],[1,7,3,6],[4,3,2,5]], minor:[[1,4,7,3],[6,5,1,4],[1,6,2,5]]},
  rock:{major:[[1,7,4,1],[1,5,4,4],[6,5,4,5],[1,4,6,5]], minor:[[1,7,6,7],[1,6,3,7],[1,4,6,5]]},
  cinematic:{major:[[1,5,6,3],[4,1,2,5],[6,4,1,5],[1,3,6,4]], minor:[[1,6,3,7],[1,4,6,7],[6,7,1,5]]},
  gospel:{major:[[1,6,2,5],[4,3,6,2],[1,5,6,2],[2,5,1,1]], minor:[[1,4,7,3],[6,2,5,1],[1,6,4,5]]}
};
function chordQuality(degree, mode, complexity, style){
  const majorTriad = {1:'',2:'m',3:'m',4:'',5:'',6:'m',7:'dim'};
  const minorTriad = {1:'m',2:'dim',3:'',4:'m',5:'m',6:'',7:''};
  let q = (mode==='major'?majorTriad:minorTriad)[degree];
  if(complexity==='sevenths' || complexity==='color'){
    if(q==='') q = style==='rnb'||style==='gospel' ? 'maj7' : (degree===5 ? '7' : 'add9');
    else if(q==='m') q = 'm7';
    else if(q==='dim') q = 'm7b5';
  }
  if(complexity==='color'){
    if(q==='m7' && Math.random()<.25) q='m9';
    if(q==='maj7' && Math.random()<.25) q='maj9';
    if(q==='7' && Math.random()<.25) q='9';
    if(q==='add9' && Math.random()<.18) q='sus2';
  }
  return q;
}
function degreeRootPc(key, mode, degree){ const scale = mode==='major'?MAJOR_DEGREES:MINOR_DEGREES; return (NOTE_TO_PC[key]+scale[degree-1])%12; }
function chordNotes(rootPc, quality, voicing){
  let intervals = [0,4,7];
  if(quality==='m') intervals=[0,3,7]; if(quality==='dim') intervals=[0,3,6]; if(quality==='maj7') intervals=[0,4,7,11]; if(quality==='maj9') intervals=[0,4,7,11,14]; if(quality==='7') intervals=[0,4,7,10]; if(quality==='9') intervals=[0,4,7,10,14]; if(quality==='add9') intervals=[0,4,7,14]; if(quality==='sus2') intervals=[0,2,7]; if(quality==='m7') intervals=[0,3,7,10]; if(quality==='m9') intervals=[0,3,7,10,14]; if(quality==='m7b5') intervals=[0,3,6,10];
  let base = 60 + rootPc; while(base>71) base-=12;
  let notes = intervals.map(i=>base+i);
  if(voicing==='open' && notes.length>=3){ notes[1]+=12; if(notes[2]>notes[1]) notes[2]-=12; notes.sort((a,b)=>a-b); }
  return notes;
}
function buildProgression(){
  const key=document.getElementById('key').value, mode=document.getElementById('mode').value, style=document.getElementById('style').value, complexity=document.getElementById('complexity').value, bars=parseInt(document.getElementById('bars').value,10), tempo=parseInt(document.getElementById('tempo').value,10), pattern=document.getElementById('pattern').value, voicing=document.getElementById('voicing').value;
  let base = choice(templates[style][mode]); let degrees=[]; while(degrees.length<bars) degrees=degrees.concat(base); degrees=degrees.slice(0,bars);
  if(bars>=8 && Math.random()<.45){ degrees[degrees.length-2] = weighted([{v:5,w:4},{v:4,w:2},{v:2,w:2},{v:7,w:1}]); degrees[degrees.length-1]=1; }
  const labels = mode==='major'?DEGREE_LABELS:MINOR_LABELS;
  const chords = degrees.map((d,i)=>{ const pc=degreeRootPc(key,mode,d); const quality=chordQuality(d,mode,complexity,style); return {degree:d,roman:labels[d-1],name:PC_TO_SHARP[pc]+quality,rootPc:pc,quality,notes:chordNotes(pc,quality,voicing),bar:i+1}; });
  current={key,mode,style,complexity,bars,tempo,pattern,voicing,chords}; render();
}
function render(){ const el=document.getElementById('result'); el.innerHTML='<div class="progression">'+current.chords.map(c=>`<div class="chord"><span>Bar ${c.bar} • ${c.roman}</span><strong>${c.name}</strong><span>${c.notes.map(n=>PC_TO_SHARP[n%12]).join(' · ')}</span></div>`).join('')+'</div><div class="note small"><strong>Progression:</strong> '+current.chords.map(c=>c.name).join(' | ')+'<br><strong>Roman numerals:</strong> '+current.chords.map(c=>c.roman).join(' - ')+'</div>'; ['playBtn','downloadBtn'].forEach(id=>document.getElementById(id).disabled=false); }
function stopAudio(){ playingNodes.forEach(n=>{try{n.stop()}catch(e){}}); playingNodes=[]; document.getElementById('stopBtn').disabled=true; }
function play(){ if(!current)return; stopAudio(); audioCtx = audioCtx || new (window.AudioContext||window.webkitAudioContext)(); const beat=60/current.tempo; const bar=beat*4; let t=audioCtx.currentTime+.05; current.chords.forEach(c=>{ c.notes.forEach((m,idx)=>{ const osc=audioCtx.createOscillator(), gain=audioCtx.createGain(); osc.type='triangle'; osc.frequency.value=440*Math.pow(2,(m-69)/12); gain.gain.setValueAtTime(0,t); gain.gain.linearRampToValueAtTime(idx===0?.09:.065,t+.03); gain.gain.exponentialRampToValueAtTime(.0001,t+bar*.92); osc.connect(gain).connect(audioCtx.destination); osc.start(t); osc.stop(t+bar*.95); playingNodes.push(osc); }); t+=bar; }); document.getElementById('stopBtn').disabled=false; setTimeout(()=>document.getElementById('stopBtn').disabled=true,current.chords.length*bar*1000+300); }
function vlq(n){ let b=n&0x7F, bytes=[]; while(n>>=7){ b<<=8; b|=((n&0x7F)|0x80); } while(true){ bytes.push(b&0xFF); if(b&0x80) b>>=8; else break; } return bytes; }
function strBytes(s){ return [...s].map(ch=>ch.charCodeAt(0)); } function u32(n){return [(n>>24)&255,(n>>16)&255,(n>>8)&255,n&255]} function u16(n){return [(n>>8)&255,n&255]}
function midiBytes(){ const ppq=480, tempoUs=Math.round(60000000/current.tempo); let track=[]; track.push(0,0xFF,0x51,3,(tempoUs>>16)&255,(tempoUs>>8)&255,tempoUs&255); track.push(0,0xC0,0); const barTicks=ppq*4; current.chords.forEach(c=>{ c.notes.forEach((m,i)=>{ track.push(...vlq(i===0?0:0),0x90,m, i===0?86:72); }); c.notes.forEach((m,i)=>{ track.push(...vlq(i===0?barTicks:0),0x80,m,0); }); }); track.push(0,0xFF,0x2F,0); return new Uint8Array([...strBytes('MThd'),...u32(6),...u16(0),...u16(1),...u16(ppq),...strBytes('MTrk'),...u32(track.length),...track]); }
function downloadMidi(){ if(!current)return; const blob=new Blob([midiBytes()],{type:'audio/midi'}); const a=document.createElement('a'); a.href=URL.createObjectURL(blob); a.download=`chord-idea-${current.key}-${current.mode}-${Date.now()}.mid`; document.body.appendChild(a); a.click(); a.remove(); }
document.getElementById('generateBtn').addEventListener('click',buildProgression); document.getElementById('playBtn').addEventListener('click',play); document.getElementById('stopBtn').addEventListener('click',stopAudio); document.getElementById('downloadBtn').addEventListener('click',downloadMidi); buildProgression();
</script>
</body>
</html>
