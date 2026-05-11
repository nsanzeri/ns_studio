<?php
/**
 * Ready Set Shows - Parallel Mode Chord Generator MVP
 * Drop-in path: /studio/chords/mode-swap.php
 * Self-contained: no DB, no auth, no shared includes required.
 */
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Parallel Mode Chord Generator | Ready Set Shows</title>
  <style>
    :root{--bg:#090b12;--panel:#111827;--text:#f8fafc;--muted:#aeb8cc;--line:rgba(255,255,255,.13);--accent:#38bdf8;--accent2:#a78bfa;--gold:#fbbf24;--green:#86efac;--rose:#fb7185}
    *{box-sizing:border-box}body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:radial-gradient(circle at top left,#15364b 0,#111827 42%,#06070b 100%);color:var(--text);line-height:1.45}.wrap{max-width:1180px;margin:0 auto;padding:28px 18px 56px}.hero{display:grid;grid-template-columns:1.05fr .95fr;gap:18px;margin-bottom:18px}.card{background:linear-gradient(180deg,rgba(255,255,255,.075),rgba(255,255,255,.032));border:1px solid var(--line);border-radius:22px;box-shadow:0 18px 50px rgba(0,0,0,.32);padding:22px}.eyebrow{text-transform:uppercase;letter-spacing:.14em;font-size:12px;color:var(--accent);font-weight:900}h1{font-size:clamp(34px,5vw,58px);line-height:.98;margin:10px 0 14px}h2{font-size:22px;margin:0 0 14px}p{color:var(--muted);margin:0 0 12px}.grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px}.field{display:flex;flex-direction:column;gap:7px}label{font-size:13px;color:#e5e7eb;font-weight:800}select,input{width:100%;background:#0b1020;color:var(--text);border:1px solid var(--line);border-radius:14px;padding:11px 12px;font:inherit}button{border:0;border-radius:16px;padding:12px 16px;font-weight:900;cursor:pointer;color:#06111a;background:linear-gradient(135deg,var(--accent),var(--accent2));box-shadow:0 10px 28px rgba(56,189,248,.22)}button.secondary{background:#182236;color:var(--text);border:1px solid var(--line);box-shadow:none}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}button:disabled{opacity:.45;cursor:not-allowed}.progression{margin-top:18px;display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.measure{min-height:132px;background:#0b1020;border:1px solid var(--line);border-radius:18px;padding:14px;display:flex;flex-direction:column;justify-content:space-between}.measure strong{font-size:29px;letter-spacing:-.02em}.measure span{color:var(--muted);font-size:13px}.modeTag{display:inline-block;border-radius:999px;padding:5px 8px;font-size:12px;background:rgba(56,189,248,.12);border:1px solid rgba(56,189,248,.22);color:#dff6ff}.home{border-color:rgba(251,191,36,.6);background:rgba(251,191,36,.11)}.borrowed{border-color:rgba(134,239,172,.45);background:rgba(134,239,172,.08)}.pillrow{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}.pill{font-size:12px;color:#e5e7eb;background:rgba(56,189,248,.12);border:1px solid rgba(56,189,248,.25);border-radius:999px;padding:7px 10px}.note{margin-top:14px;background:rgba(56,189,248,.08);border:1px solid rgba(56,189,248,.22);border-radius:16px;padding:13px;color:#dff6ff}.small{font-size:13px;color:var(--muted)}.footer{margin-top:18px;color:var(--muted);font-size:13px}@media(max-width:960px){.hero{grid-template-columns:1fr}.grid{grid-template-columns:repeat(2,1fr)}.progression{grid-template-columns:repeat(2,1fr)}}@media(max-width:560px){.grid,.progression{grid-template-columns:1fr}.actions button{width:100%}}
  </style>
</head>
<body>
<main class="wrap">
  <section class="hero">
    <div class="card">
      <div class="eyebrow">Ready Set Shows Lab</div>
      <h1>Parallel Mode Generator</h1>
      <p>Generate a progression in one selected key, but borrow the non-I chords from a selected parallel mode. You can also mix modes for stranger, more songwriter-friendly color.</p>
      <div class="pillrow"><div class="pill">Keeps the I chord anchored</div><div class="pill">Varied endings</div><div class="pill">Swaps the other degrees</div><div class="pill">Selectable modes</div><div class="pill">MIDI download</div></div>
    </div>
    <div class="card">
      <h2>How this one thinks</h2>
      <p>The one chord stays anchored to the selected key, but the progression does not always have to end there. Other chords can be pulled from Ionian, Dorian, Phrygian, Lydian, Mixolydian, Aeolian, or Locrian built from the same root.</p>
      <p class="small">Example in C: keep <strong>Cmaj7</strong>, then borrow chords like <strong>Dm7</strong> from Dorian, <strong>Bbmaj7</strong> from Mixolydian, or <strong>Abmaj7</strong> from Aeolian.</p>
    </div>
  </section>

  <section class="card">
    <h2>Generator Controls</h2>
    <div class="grid">
      <div class="field"><label for="key">Key</label><select id="key"><option>C</option><option>Db</option><option>D</option><option>Eb</option><option>E</option><option>F</option><option>Gb</option><option>G</option><option>Ab</option><option>A</option><option>Bb</option><option>B</option></select></div>
      <div class="field"><label for="homeQuality">I Chord</label><select id="homeQuality"><option value="maj7" selected>maj7</option><option value="6">6</option><option value="maj9">maj9</option><option value="m7">m7</option></select></div>
      <div class="field"><label for="mode">Borrow Mode</label><select id="mode"><option>Ionian</option><option selected>Dorian</option><option>Phrygian</option><option>Lydian</option><option>Mixolydian</option><option>Aeolian</option><option>Locrian</option></select></div>
      <div class="field"><label for="mixModes">Mix Modes</label><select id="mixModes"><option value="no" selected>No - selected mode only</option><option value="yes">Yes - mix parallel modes</option></select></div>
      <div class="field"><label for="bars">Bars</label><select id="bars"><option>4</option><option selected>8</option><option>12</option><option>16</option></select></div>
      <div class="field"><label for="tempo">Tempo</label><input id="tempo" type="number" min="50" max="210" value="105"></div>
      <div class="field"><label for="density">Home Chord Frequency</label><select id="density"><option value="light">Light</option><option value="medium" selected>Medium</option><option value="heavy">Heavy</option></select></div>
      <div class="field"><label for="sevenths">Chord Color</label><select id="sevenths"><option value="triads">Triads</option><option value="sevenths" selected>7ths</option><option value="ninths">9ths sometimes</option></select></div>
    </div>
    <div class="actions">
      <button id="generateBtn">Generate Mode Progression</button>
      <button id="playBtn" class="secondary" disabled>Play Preview</button>
      <button id="stopBtn" class="secondary" disabled>Stop</button>
      <button id="downloadBtn" class="secondary" disabled>Download MIDI</button>
    </div>
    <div id="result"></div>
  </section>
  <div class="footer">First pass: this is intentionally rule-based and readable, so the musical taste can be adjusted chord by chord.</div>
</main>
<script>
const PC_TO_NAME_FLAT=['C','Db','D','Eb','E','F','Gb','G','Ab','A','Bb','B'];
const NOTE_TO_PC={C:0,Db:1,D:2,Eb:3,E:4,F:5,Gb:6,G:7,Ab:8,A:9,Bb:10,B:11};
const MODES={
  Ionian:[0,2,4,5,7,9,11], Dorian:[0,2,3,5,7,9,10], Phrygian:[0,1,3,5,7,8,10],
  Lydian:[0,2,4,6,7,9,11], Mixolydian:[0,2,4,5,7,9,10], Aeolian:[0,2,3,5,7,8,10], Locrian:[0,1,3,5,6,8,10]
};
const MODE_NAMES=Object.keys(MODES), ROMAN=['I','II','III','IV','V','VI','VII'];
let current=null,audioCtx=null,playingNodes=[];
function pcName(pc){return PC_TO_NAME_FLAT[((pc%12)+12)%12];}
function choice(a){return a[Math.floor(Math.random()*a.length)]}
function chance(p){return Math.random()<p}
function homeProb(v){return v==='heavy'?.38:v==='medium'?.24:.13}
function degreePc(rootPc,modeName,degree){return (rootPc+MODES[modeName][degree-1])%12}
function modeChordQuality(modeName,degree,color){
  const scale=MODES[modeName], root=scale[degree-1];
  const third=(scale[(degree+1)%7]-root+12)%12, fifth=(scale[(degree+3)%7]-root+12)%12, seventh=(scale[(degree+5)%7]-root+12)%12;
  let q='';
  if(third===4 && fifth===7) q='';
  else if(third===3 && fifth===7) q='m';
  else if(third===3 && fifth===6) q='dim';
  else if(third===4 && fifth===8) q='aug';
  if(color==='triads') return q;
  if(seventh===11) q += 'maj7';
  else if(seventh===10) q += (q==='dim' ? '7' : '7');
  else q += '6';
  if(color==='ninths' && chance(.28) && !q.includes('dim')) q=q.replace('maj7','maj9').replace('m7','m9').replace('7','9');
  return q;
}
function romanFor(modeName,degree,q){
  let r=ROMAN[degree-1];
  if(q.startsWith('m')) r=r.toLowerCase();
  if(q.startsWith('dim')) r=r.toLowerCase()+'°';
  if(q.startsWith('aug')) r=r+'+';
  const semi=MODES[modeName][degree-1], ionian=[0,2,4,5,7,9,11][degree-1];
  if(semi<ionian) r='b'+r; if(semi>ionian) r='#'+r;
  return r;
}
function makeHome(rootPc){
  const q=document.getElementById('homeQuality').value;
  return {name:pcName(rootPc)+q, rootPc, quality:q, roman:'I', mode:'Home', type:'home'};
}
function makeBorrowed(rootPc,modeName,degree,color){
  const pc=degreePc(rootPc,modeName,degree), q=modeChordQuality(modeName,degree,color);
  return {name:pcName(pc)+q, rootPc:pc, quality:q || 'maj', roman:romanFor(modeName,degree,q), mode:modeName, type:'borrowed'};
}
function buildProgression(){
  const key=document.getElementById('key').value, rootPc=NOTE_TO_PC[key], selectedMode=document.getElementById('mode').value;
  const mix=document.getElementById('mixModes').value==='yes', bars=parseInt(document.getElementById('bars').value,10), tempo=parseInt(document.getElementById('tempo').value,10);
  const color=document.getElementById('sevenths').value, density=document.getElementById('density').value;
  const degrees=[2,3,4,5,6,7]; // never replace I; I is always the home chord.
  const cadential=[4,5,6,7];
  let measures=[];
  for(let i=0;i<bars;i++){
    if(i===0){measures.push(makeHome(rootPc)); continue;}

    // Ending rule: give the progression a home option, but do not force every
    // generation to resolve to I. This creates more usable loops and open-ended
    // songwriter prompts.
    if(i===bars-1 && chance(.35)){measures.push(makeHome(rootPc)); continue;}

    if(i!==bars-1 && chance(homeProb(density))){measures.push(makeHome(rootPc)); continue;}

    const modeName=mix ? choice(MODE_NAMES.filter(m=>m!=='Locrian' || chance(.35))) : selectedMode;
    const pool=(i>=bars-2)?cadential:degrees;
    let chord=makeBorrowed(rootPc,modeName,choice(pool),color);
    // Avoid too much diminished weirdness unless Locrian was intentionally selected/mixed.
    if(chord.quality.includes('dim') && modeName!=='Locrian' && chance(.75)) chord=makeBorrowed(rootPc,modeName,choice([2,3,4,5,6]),color);
    measures.push(chord);
  }
  current={key,rootPc,bars,tempo,measures,mix,selectedMode,color}; render();
}
function intervals(q){
  if(q==='maj'||q==='')return[0,4,7]; if(q==='m')return[0,3,7]; if(q==='dim')return[0,3,6]; if(q==='aug')return[0,4,8];
  if(q==='6')return[0,4,7,9]; if(q==='maj7')return[0,4,7,11]; if(q==='maj9')return[0,4,7,11,14];
  if(q==='m7')return[0,3,7,10]; if(q==='m9')return[0,3,7,10,14]; if(q==='7')return[0,4,7,10]; if(q==='9')return[0,4,7,10,14]; if(q==='dim7')return[0,3,6,9];
  return[0,4,7];
}
function notesFor(ch){let base=60+ch.rootPc;while(base>71)base-=12;let ns=intervals(ch.quality).map(i=>base+i); if(ns.length>3){ns[1]+=12;ns.sort((a,b)=>a-b)} return ns;}
function render(){
  const el=document.getElementById('result');
  el.innerHTML='<div class="progression">'+current.measures.map((m,i)=>`<div class="measure ${m.type}"><span>Measure ${i+1} • <em class="modeTag">${m.mode}</em></span><strong>${m.name}</strong><span>${m.roman}</span></div>`).join('')+'</div><div class="note small"><strong>Progression:</strong> '+current.measures.map(m=>m.name).join(' | ')+'<br><strong>Rule check:</strong> The I chord remains the selected home chord, but the ending can land on I or a borrowed color chord. Every other chord is borrowed from '+(current.mix?'mixed parallel modes.':'parallel '+current.selectedMode+'.')+'</div>';
  document.getElementById('playBtn').disabled=false; document.getElementById('downloadBtn').disabled=false;
}
function stopAudio(){playingNodes.forEach(n=>{try{n.stop()}catch(e){}});playingNodes=[];document.getElementById('stopBtn').disabled=true;}
function play(){if(!current)return;stopAudio();audioCtx=audioCtx||new(window.AudioContext||window.webkitAudioContext)();const beat=60/current.tempo,bar=beat*4;let t=audioCtx.currentTime+.05;current.measures.forEach(m=>{const dur=bar;notesFor(m).forEach((note,idx)=>{const osc=audioCtx.createOscillator(),gain=audioCtx.createGain();osc.type='triangle';osc.frequency.value=440*Math.pow(2,(note-69)/12);gain.gain.setValueAtTime(0,t);gain.gain.linearRampToValueAtTime(idx===0?.09:.06,t+.025);gain.gain.exponentialRampToValueAtTime(.0001,t+dur*.92);osc.connect(gain).connect(audioCtx.destination);osc.start(t);osc.stop(t+dur*.95);playingNodes.push(osc);});t+=dur;});document.getElementById('stopBtn').disabled=false;setTimeout(()=>document.getElementById('stopBtn').disabled=true,current.bars*bar*1000+300);}
function vlq(n){let b=n&0x7F,bytes=[];while(n>>=7){b<<=8;b|=((n&0x7F)|0x80)}while(true){bytes.push(b&255);if(b&0x80)b>>=8;else break}return bytes}
function strBytes(s){return[...s].map(c=>c.charCodeAt(0))}function u32(n){return[(n>>24)&255,(n>>16)&255,(n>>8)&255,n&255]}function u16(n){return[(n>>8)&255,n&255]}
function midiBytes(){const ppq=480,tempoUs=Math.round(60000000/current.tempo);let track=[];track.push(0,0xFF,0x51,3,(tempoUs>>16)&255,(tempoUs>>8)&255,tempoUs&255);track.push(0,0xC0,0);current.measures.forEach(m=>{const dur=ppq*4,ns=notesFor(m);ns.forEach((note,i)=>track.push(...vlq(i===0?0:0),0x90,note,i===0?86:72));ns.forEach((note,i)=>track.push(...vlq(i===0?dur:0),0x80,note,0));});track.push(0,0xFF,0x2F,0);return new Uint8Array([...strBytes('MThd'),...u32(6),...u16(0),...u16(1),...u16(ppq),...strBytes('MTrk'),...u32(track.length),...track])}
function downloadMidi(){if(!current)return;const blob=new Blob([midiBytes()],{type:'audio/midi'});const a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=`parallel-mode-${current.key}-${Date.now()}.mid`;document.body.appendChild(a);a.click();a.remove();}
document.getElementById('generateBtn').addEventListener('click',buildProgression);document.getElementById('playBtn').addEventListener('click',play);document.getElementById('stopBtn').addEventListener('click',stopAudio);document.getElementById('downloadBtn').addEventListener('click',downloadMidi);buildProgression();
</script>
</body>
</html>
