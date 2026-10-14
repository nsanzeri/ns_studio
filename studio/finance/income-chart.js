(() => {
  const source = document.getElementById('finance-chart-data');
  if (!source) return;
  const rows = JSON.parse(source.textContent);
  const wrap = source.closest('.finance-chart-wrap');
  const svg = wrap.querySelector('svg');
  const points = svg.querySelector('polyline').points;
  const selection = svg.querySelector('.finance-chart-selection');
  const line = selection.querySelector('line');
  const dot = selection.querySelector('circle');
  const tooltip = wrap.querySelector('.finance-chart-tooltip');
  const money = cents => new Intl.NumberFormat('en-US',{style:'currency',currency:'USD'}).format(cents/100);
  let index = 0;
  function select(next) {
    index = Math.max(0,Math.min(rows.length-1,next));
    const row = rows[index];
    const point = points.getItem(index);
    line.setAttribute('x1',point.x); line.setAttribute('x2',point.x);
    dot.setAttribute('cx',point.x); dot.setAttribute('cy',point.y);
    selection.setAttribute('visibility','visible');
    const heading = document.createElement('strong'); heading.textContent = row.label;
    const monthly = document.createElement('span'); monthly.textContent = 'Month: ' + money(row.gross_cents);
    const yearly = document.createElement('span'); yearly.textContent = row.year + ' through this month: ' + money(row.ytd_cents);
    tooltip.replaceChildren(heading,monthly,yearly);
    tooltip.hidden = false;
    const screen = new DOMPoint(point.x,point.y).matrixTransform(svg.getScreenCTM());
    const bounds = wrap.getBoundingClientRect();
    tooltip.style.left = Math.max(10,Math.min(bounds.width-tooltip.offsetWidth-10,screen.x-bounds.left-tooltip.offsetWidth/2)) + 'px';
    tooltip.style.top = Math.max(8,screen.y-bounds.top-tooltip.offsetHeight-14) + 'px';
    svg.setAttribute('aria-valuenow',index+1);
    svg.setAttribute('aria-valuetext',row.label + ', gross ' + money(row.gross_cents) + ', year to date ' + money(row.ytd_cents));
  }
  function pointer(event) {
    const local = new DOMPoint(event.clientX,event.clientY).matrixTransform(svg.getScreenCTM().inverse());
    let nearest = 0;
    for(let n=1;n<points.numberOfItems;n++) {
      if(Math.abs(points.getItem(n).x-local.x)<Math.abs(points.getItem(nearest).x-local.x)) nearest=n;
    }
    select(nearest);
  }
  function hide() { tooltip.hidden=true; selection.setAttribute('visibility','hidden'); }
  svg.addEventListener('pointermove',pointer);
  svg.addEventListener('pointerdown',pointer);
  svg.addEventListener('pointerleave',event=>{ if(event.pointerType !== 'touch' && document.activeElement!==svg) hide(); });
  svg.addEventListener('focus',()=>select(index));
  svg.addEventListener('blur',hide);
  svg.addEventListener('keydown',event=>{
    const steps={ArrowLeft:index-1,ArrowDown:index-1,ArrowRight:index+1,ArrowUp:index+1,Home:0,End:rows.length-1};
    if(event.key in steps) { event.preventDefault(); select(steps[event.key]); }
    if(event.key==='Escape') hide();
  });
  window.addEventListener('resize',()=>{if(!tooltip.hidden) select(index);});
})();
