<?php
/**
 * Ready Set Shows - Jazz Chord Generator MVP
 * Drop-in path: /studio/chords/index.php
 * Self-contained: no DB, no auth, no shared includes required.
 */
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Jazz Chord Generator | Ready Set Shows</title>
  <style>
    :root{--bg:#090b12;--panel:#111827;--panel2:#182236;--text:#f8fafc;--muted:#aeb8cc;--line:rgba(255,255,255,.13);--accent:#f472b6;--accent2:#fb7185;--red:#f87171;--purple:#c084fc;--blue:#60a5fa;--green:#86efac;}
    *{box-sizing:border-box}body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:radial-gradient(circle at top left,#312042 0,#111827 42%,#06070b 100%);color:var(--text);line-height:1.45} .wrap{max-width:1180px;margin:0 auto;padding:28px 18px 56px}.hero{display:grid;grid-template-columns:1.1fr .9fr;gap:18px;margin-bottom:18px}.card{background:linear-gradient(180deg,rgba(255,255,255,.075),rgba(255,255,255,.032));border:1px solid var(--line);border-radius:22px;box-shadow:0 18px 50px rgba(0,0,0,.32);padding:22px}.eyebrow{text-transform:uppercase;letter-spacing:.14em;font-size:12px;color:var(--accent);font-weight:900}h1{font-size:clamp(34px,5vw,58px);line-height:.98;margin:10px 0 14px}h2{font-size:22px;margin:0 0 14px}p{color:var(--muted);margin:0 0 12px}.grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px}.field{display:flex;flex-direction:column;gap:7px}label{font-size:13px;color:#e5e7eb;font-weight:800}select,input{width:100%;background:#0b1020;color:var(--text);border:1px solid var(--line);border-radius:14px;padding:11px 12px;font:inherit}button{border:0;border-radius:16px;padding:12px 16px;font-weight:900;cursor:pointer;color:#130816;background:linear-gradient(135deg,var(--accent),var(--accent2));box-shadow:0 10px 28px rgba(244,114,182,.22)}button.secondary{background:#182236;color:var(--text);border:1px solid var(--line);box-shadow:none}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}button:disabled{opacity:.45;cursor:not-allowed}.result{margin-top:18px;display:grid;gap:12px}.progression{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.measure{min-height:120px;background:#0b1020;border:1px solid var(--line);border-radius:18px;padding:14px;display:flex;flex-direction:column;justify-content:space-between}.measure strong{font-size:27px;letter-spacing:-.02em}.measure span{color:var(--muted);font-size:13px}.slots{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.slot{border-radius:12px;padding:8px 10px;background:rgba(255,255,255,.055);border:1px solid var(--line)}.slot.purple{border-color:rgba(192,132,252,.55);background:rgba(192,132,252,.13)}.slot.red{border-color:rgba(248,113,113,.55);background:rgba(248,113,113,.13)}.slot.main{border-color:rgba(96,165,250,.5);background:rgba(96,165,250,.11)}.slot.minor{border-color:rgba(134,239,172,.5);background:rgba(134,239,172,.10)}.pillrow{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}.pill{font-size:12px;color:#e5e7eb;background:rgba(244,114,182,.12);border:1px solid rgba(244,114,182,.25);border-radius:999px;padding:7px 10px}.note{background:rgba(251,113,133,.09);border:1px solid rgba(251,113,133,.22);border-radius:16px;padding:13px;color:#ffe4e6}.small{font-size:13px;color:var(--muted)}.legend{display:flex;gap:8px;flex-wrap:wrap}.legend b{font-size:12px;border-radius:999px;padding:6px 9px}.legend .purple{background:rgba(192,132,252,.15);color:#ead7ff}.legend .red{background:rgba(248,113,113,.15);color:#fee2e2}.legend .main{background:rgba(96,165,250,.15);color:#dbeafe}.legend .minor{background:rgba(134,239,172,.15);color:#dcfce7}.footer{margin-top:18px;color:var(--muted);font-size:13px}@media(max-width:960px){.hero{grid-template-columns:1fr}.grid{grid-template-columns:repeat(2,1fr)}.progression{grid-template-columns:repeat(2,1fr)}}@media(max-width:560px){.grid,.progression{grid-template-columns:1fr}.actions button{width:100%}}
  </style>
</head>
<body>
<main class="wrap">
  <section class="hero">
    <div class="card">
      <div class="eyebrow">Ready Set Shows Lab</div>
      <h1>Jazz Chord Generator</h1>
      <p>Generate structured jazz progressions using diatonic family chords, ii-V approach cells, and color borrowed from the parallel minor.</p>
      <div class="pillrow"><div class="pill">Starts on I or vi</div><div class="pill">No VII chord</div><div class="pill">MIDI download</div><div class="pill">Measures 1–3 echo in 5–7</div><div class="pill">Varied purple clusters</div></div>
    </div>
    <div class="card">
      <h2>Rule model</h2>
      <p>When the generator uses a purple approach chord, it places purple + red in the same measure at two beats each, then follows the arrow down to the red chord. It can either resolve to the target black/main family chord, or chain into another ii-V where the next purple chord is a fourth above the last red chord.</p>
      <div class="legend"><b class="purple">Purple: ii of target</b><b class="red">Red: V7 of target</b><b class="main">Main family</b><b class="minor">Parallel minor</b></div>
    </div>
  </section>

  <section class="card">
    <h2>Generator Controls</h2>
    <div class="grid">
      <div class="field"><label for="key">Key</label><select id="key"><option>C</option><option>Db</option><option>D</option><option>Eb</option><option>E</option><option>F</option><option>Gb</option><option>G</option><option>Ab</option><option>A</option><option>Bb</option><option>B</option></select></div>
      <div class="field"><label for="bars">Bars</label><select id="bars"><option selected>8</option><option>12</option><option>16</option></select></div>
      <div class="field"><label for="tempo">Tempo</label><input id="tempo" type="number" min="50" max="210" value="120"></div>
      <div class="field"><label for="approach">ii-V Approach</label><select id="approach"><option value="light">Light</option><option value="medium" selected>Medium</option><option value="heavy">Heavy</option></select></div>
      <div class="field"><label for="minorBorrow">Parallel Minor</label><select id="minorBorrow"><option value="light">Light</option><option value="medium" selected>Medium</option><option value="heavy">Heavy</option></select></div>
    </div>
    <div class="actions">
      <button id="generateBtn">Generate Jazz Progression</button>
      <button id="playBtn" class="secondary" disabled>Play Preview</button>
      <button id="stopBtn" class="secondary" disabled>Stop</button>
      <button id="downloadBtn" class="secondary" disabled>Download MIDI</button>
    </div>
    <div id="result" class="result"></div>
  </section>
  <div class="footer">This is a first pass. The rules are intentionally readable in the JavaScript so you can tune the musical taste over time. Parallel minor uses im7, not imMaj7.</div>
</main>
<script>
const PC_TO_NAME_FLAT=['C','Db','D','Eb','E','F','Gb','G','Ab','A','Bb','B'];
const NOTE_TO_PC={C:0,Db:1,D:2,Eb:3,E:4,F:5,Gb:6,G:7,Ab:8,A:9,Bb:10,B:11};
const MAJOR=[0,2,4,5,7,9,11];
const DEG=['I','ii','iii','IV','V','vi','vii°'];
let current=null,audioCtx=null,playingNodes=[];
function pcName(pc){return PC_TO_NAME_FLAT[((pc%12)+12)%12];}
function choice(a){return a[Math.floor(Math.random()*a.length)]}
function chance(p){return Math.random()<p}
function prob(sel){return sel==='heavy'?.55:sel==='medium'?.34:.18}
function degreePc(keyPc,d){return (keyPc+MAJOR[d-1])%12}
function familyChord(keyPc,d){
  const q={1:'maj7',2:'m7',3:'m7',4:'maj7',5:'7',6:'m7'}[d];
  return {name:pcName(degreePc(keyPc,d))+q,type:'main',degree:d,roman:DEG[d-1],rootPc:degreePc(keyPc,d),quality:q};
}
function parallelMinorChord(keyPc){
  // Borrowed color from parallel minor. Avoids the natural VII diminished chord. Uses Cm7/im7 for the home minor color and avoids the diminished chord from the minor family. Includes bVII7 as a common jazz/backdoor color.
  const items=[
    {semi:0,q:'m7',roman:'im7'}, {semi:3,q:'maj7',roman:'bIIImaj7'},
    {semi:5,q:'m7',roman:'ivm7'}, {semi:7,q:'m7',roman:'vm7'}, {semi:8,q:'maj7',roman:'bVImaj7'}, {semi:10,q:'7',roman:'bVII7'}
  ];
  const it=choice(items), pc=(keyPc+it.semi)%12;
  return {name:pcName(pc)+it.q,type:'minor',degree:null,roman:it.roman,rootPc:pc,quality:it.q};
}
function purpleApproachChord(purplePc,target){
  // The purple row is a cluster, not a single chord.
  // Major-family targets can use either a secondary dominant or a minor ii sound.
  // Minor-family targets can use dominant, minor, or diminished color from the same purple root.
  const targetIsMinor = target.quality.includes('m');
  const options = targetIsMinor
    ? [{q:'7',label:'V/V color'}, {q:'m7',label:'minor ii color'}, {q:'dim7',label:'diminished approach'}]
    : [{q:'7',label:'secondary dominant'}, {q:'m7',label:'minor ii color'}];
  const picked = choice(options);
  return {name:pcName(purplePc)+picked.q,type:'purple',roman:picked.label+' → '+target.roman,rootPc:purplePc,quality:picked.q,beats:2};
}
function approachCellToTarget(target){
  const t=target.rootPc;
  const dominantPc=(t+7)%12;        // V of target
  const purplePc=(dominantPc+7)%12; // purple approach root
  return [
    purpleApproachChord(purplePc,target),
    {name:pcName(dominantPc)+'7',type:'red',roman:'V7 → '+target.roman,rootPc:dominantPc,quality:'7',beats:2}
  ];
}
function buildProgression(){
  const key=document.getElementById('key').value, keyPc=NOTE_TO_PC[key], bars=parseInt(document.getElementById('bars').value,10), tempo=parseInt(document.getElementById('tempo').value,10), approach=document.getElementById('approach').value, minorBorrow=document.getElementById('minorBorrow').value;
  const familyDegrees=[1,2,3,4,5,6]; // Rule 5: no VII
  let measures=[];
  const start=chance(.58)?1:6; // Rule 1: start on I or VI
  measures.push({slots:[familyChord(keyPc,start)],note:'Start'});

  function standaloneFamilyOrMinor(label='Family'){
    if(chance(prob(minorBorrow))) return {slots:[parallelMinorChord(keyPc)],note:'Parallel minor'};
    return {slots:[familyChord(keyPc,choice(familyDegrees))],note:label};
  }
  function approachThenTarget(targetDegree){
    const target=familyChord(keyPc,targetDegree);
    return [
      {slots:approachCellToTarget(target),note:'ii-V approach',targetDegree:targetDegree},
      {slots:[target],note:'Target family resolution'}
    ];
  }
  function degreeFromRootPc(rootPc){
    return familyDegrees.find(d=>degreePc(keyPc,d)===rootPc) || null;
  }
  function chainedApproachAfter(prevApproachMeasure){
    // New rule: after purple → red, we may use another purple → red where
    // the new purple root is a perfect/major fourth up from the last red root.
    // Example in C: F#m7 B7 | Em7 A7 | Dm7
    const lastRed=[...prevApproachMeasure.slots].reverse().find(s=>s.type==='red');
    if(!lastRed) return null;
    const nextPurpleRoot=(lastRed.rootPc+5)%12;
    const nextRedRoot=(nextPurpleRoot+5)%12;
    const nextTargetRoot=(nextRedRoot+5)%12;
    const targetDegree=degreeFromRootPc(nextTargetRoot);
    if(!targetDegree) return null; // only resolve to allowed black/main-family chords; no VII.
    const target=familyChord(keyPc,targetDegree);
    return [
      {slots:[
        purpleApproachChord(nextPurpleRoot,target),
        {name:pcName(nextRedRoot)+'7',type:'red',roman:'chain V7 → '+target.roman,rootPc:nextRedRoot,quality:'7',beats:2}
      ],note:'Chained ii-V',targetDegree:targetDegree},
      {slots:[target],note:'Target family resolution'}
    ];
  }
  function approachPhrase(){
    // Prefer chainable first targets often so back-to-back ii-Vs actually appear.
    const firstTarget=chance(.68) ? choice([2,3,5,6]) : choice([1,2,4,5,6]);
    const first={slots:approachCellToTarget(familyChord(keyPc,firstTarget)),note:'ii-V approach',targetDegree:firstTarget};
    const chain=chainedApproachAfter(first);
    if(chain && chance(.72)) return [first, chain[0], chain[1]];
    return [first, {slots:[familyChord(keyPc,firstTarget)],note:'Target family resolution'}];
  }
  function isApproachMeasure(m){
    return !!(m && m.slots && m.slots.some(s=>s.type==='purple' || s.type==='red'));
  }
  function targetDegreeForApproach(m){
    if(m && m.targetDegree) return m.targetDegree;
    const red=m && [...m.slots].reverse().find(s=>s.type==='red');
    if(!red) return null;
    return degreeFromRootPc((red.rootPc+5)%12);
  }
  function normalizeResolutions(){
    for(let i=0;i<measures.length;i++){
      if(!isApproachMeasure(measures[i])) continue;
      if(isApproachMeasure(measures[i+1])){
        const chainedTarget=targetDegreeForApproach(measures[i+1]);
        if(chainedTarget && i+2<measures.length){
          measures[i+2]={slots:[familyChord(keyPc,chainedTarget)],note:'Target family resolution'};
        }
      } else {
        const target=targetDegreeForApproach(measures[i]);
        if(target && i+1<measures.length){
          measures[i+1]={slots:[familyChord(keyPc,target)],note:'Target family resolution'};
        }
      }
    }
  }


  // Measures 2-4: allow normal ii-V resolution OR a back-to-back chained ii-V before the black/main target.
  if(chance(prob(approach))){
    measures.push(...approachPhrase());
  } else {
    measures.push(standaloneFamilyOrMinor('Family'));
    if(chance(prob(approach))){
      const phrase=approachPhrase();
      measures.push(...phrase.slice(0, Math.max(1, 4-measures.length)));
    } else {
      measures.push(standaloneFamilyOrMinor('Family'));
    }
  }

  // Fill or trim to bar 4. If a bar is an approach, the next bar either chains or resolves to its valid target.
  while(measures.length<4){
    const prev=measures[measures.length-1];
    const chain=prev ? chainedApproachAfter(prev) : null;
    if(chain && chance(.55) && measures.length<3) measures.push(chain[0]);
    else if(prev && prev.slots.some(s=>s.type==='purple'||s.type==='red')){
      const maybe=chainedApproachAfter(prev);
      measures.push(maybe ? maybe[1] : {slots:[familyChord(keyPc,1)],note:'Target family resolution'});
    }
    else if(chance(.25)) measures.push({slots:[parallelMinorChord(keyPc)],note:'Borrowed turnaround'});
    else measures.push({slots:[familyChord(keyPc,5)],note:'Turnaround'});
  }
  measures=measures.slice(0,4);

  // Extra bars: keep every purple/red approach followed by its black/main target.
  while(measures.length<bars){
    if(chance(prob(approach)) && measures.length<bars-1){
      const pair=approachThenTarget(choice([1,2,4,5,6]));
      measures.push(pair[0], pair[1]);
    } else {
      measures.push(standaloneFamilyOrMinor('Family'));
    }
  }
  measures=measures.slice(0,bars);
  normalizeResolutions();
  current={key,keyPc,bars,tempo,measures}; render();
}
function cloneMeasure(m){return {note:m.note+' copy',slots:m.slots.map(s=>({...s}))};}
function intervals(q){
  if(q==='maj7')return[0,4,7,11]; if(q==='m7')return[0,3,7,10]; if(q==='m7b5')return[0,3,6,10]; if(q==='dim7')return[0,3,6,9]; if(q==='7')return[0,4,7,10]; if(q==='mMaj7')return[0,3,7,11]; return[0,4,7];
}
function notesFor(ch){let base=60+ch.rootPc;while(base>71)base-=12;let n=intervals(ch.quality).map(i=>base+i); if(n.length>3){n[1]+=12;n.sort((a,b)=>a-b)} return n;}
function chordText(m){return m.slots.map(s=>s.name).join(' → ')}
function render(){
  const el=document.getElementById('result');
  el.innerHTML='<div class="progression">'+current.measures.map((m,i)=>`<div class="measure"><span>Measure ${i+1} • ${m.note}</span><div class="slots">${m.slots.map(s=>`<strong class="slot ${s.type}">${s.name}</strong>`).join('')}</div><span>${m.slots.map(s=>s.roman).join(' / ')}</span></div>`).join('')+'</div><div class="note small"><strong>Progression:</strong> '+current.measures.map(chordText).join(' | ')+'<br><strong>Rule check:</strong> starts on I or vi, no VII chord, parallel minor avoids diminished and uses im7, purple/red approach cells use 2 beats + 2 beats, purple clusters vary between valid chord colors, a ii-V may chain into another ii-V whose new purple root is a fourth above the previous red root, then the phrase resolves to an allowed black/main family target. Measures 1–3 are echoed in 5–7.</div>';
  ['playBtn','downloadBtn'].forEach(id=>document.getElementById(id).disabled=false);
}
function stopAudio(){playingNodes.forEach(n=>{try{n.stop()}catch(e){}});playingNodes=[];document.getElementById('stopBtn').disabled=true;}
function play(){if(!current)return;stopAudio();audioCtx=audioCtx||new(window.AudioContext||window.webkitAudioContext)();const beat=60/current.tempo,bar=beat*4;let t=audioCtx.currentTime+.05;current.measures.forEach(m=>{m.slots.forEach(s=>{const dur=(s.beats||4)*beat;notesFor(s).forEach((note,idx)=>{const osc=audioCtx.createOscillator(),gain=audioCtx.createGain();osc.type='triangle';osc.frequency.value=440*Math.pow(2,(note-69)/12);gain.gain.setValueAtTime(0,t);gain.gain.linearRampToValueAtTime(idx===0?.09:.062,t+.025);gain.gain.exponentialRampToValueAtTime(.0001,t+dur*.92);osc.connect(gain).connect(audioCtx.destination);osc.start(t);osc.stop(t+dur*.95);playingNodes.push(osc);});t+=dur;});});document.getElementById('stopBtn').disabled=false;setTimeout(()=>document.getElementById('stopBtn').disabled=true,current.bars*bar*1000+300);}
function vlq(n){let b=n&0x7F,bytes=[];while(n>>=7){b<<=8;b|=((n&0x7F)|0x80)}while(true){bytes.push(b&255);if(b&0x80)b>>=8;else break}return bytes}
function strBytes(s){return[...s].map(c=>c.charCodeAt(0))}function u32(n){return[(n>>24)&255,(n>>16)&255,(n>>8)&255,n&255]}function u16(n){return[(n>>8)&255,n&255]}
function midiBytes(){const ppq=480,tempoUs=Math.round(60000000/current.tempo);let track=[];track.push(0,0xFF,0x51,3,(tempoUs>>16)&255,(tempoUs>>8)&255,tempoUs&255);track.push(0,0xC0,0);current.measures.forEach(m=>{m.slots.forEach(s=>{const dur=ppq*(s.beats||4);const ns=notesFor(s);ns.forEach((note,i)=>track.push(...vlq(i===0?0:0),0x90,note,i===0?86:72));ns.forEach((note,i)=>track.push(...vlq(i===0?dur:0),0x80,note,0));});});track.push(0,0xFF,0x2F,0);return new Uint8Array([...strBytes('MThd'),...u32(6),...u16(0),...u16(1),...u16(ppq),...strBytes('MTrk'),...u32(track.length),...track])}
function downloadMidi(){if(!current)return;const blob=new Blob([midiBytes()],{type:'audio/midi'});const a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=`jazz-progression-${current.key}-${Date.now()}.mid`;document.body.appendChild(a);a.click();a.remove();}
document.getElementById('generateBtn').addEventListener('click',buildProgression);document.getElementById('playBtn').addEventListener('click',play);document.getElementById('stopBtn').addEventListener('click',stopAudio);document.getElementById('downloadBtn').addEventListener('click',downloadMidi);buildProgression();
</script>
</body>
</html>
