'use strict';

function compactRange(t) {
  const [year,month,day]=t.date.split('-');
  const [,endMonth,endDay]=t.end.split('-');
  return month===endMonth?`${day}.–${endDay}.${month}.`:`${day}.${month}.–${endDay}.${endMonth}.`;
}

function termButton(t,selected,cheapest) {
  const label=`${fmt(t.date)} do ${fmt(t.end)}, ${money(t.price)} KM${t.price===cheapest.price?', najniža cijena':''}${t.illustrative?', ogledni termin':', izvorni arhivski termin'}`;
  return `<button class="term-choice" type="button" role="radio" aria-checked="${t.id===selected.id}" aria-label="${escapeHTML(label)}" tabindex="${t.id===selected.id?'0':'-1'}" data-term-id="${t.id}"><span class="term-mark">${icon('check')}</span><span class="term-date">${compactRange(t)}</span><span class="term-cost">${t.price===cheapest.price?'<small>Najniža cijena</small>':''}<strong>${money(t.price)} <span>KM</span></strong></span></button>`;
}

function multiDateDetail(t,dates) {
  const cheapest=lowestOffer(t);
  const original=trips.find(x=>x.id==='istanbul')||t;
  const year=t.date.slice(0,4);
  main.innerHTML=`<div class="container detail multi-date-detail">
    <a class="back" href="#putovanja">${icon('left')} Sva putovanja</a>
    <div class="detail-head"><div><h1>${escapeHTML(original.title)}</h1><p>${escapeHTML(original.description)}</p></div><div class="detail-from"><small>Već od</small><strong>${money(cheapest.price)} <span>KM</span></strong><span>po osobi · ogledni prikaz</span><a href="#termini">Odaberite termin ${icon('right')}</a></div></div>
    <div class="trip-overview">
      <figure class="trip-overview-photo"><img src="${safeImage(original.image)}" alt="${escapeHTML(original.title)}" width="850" height="800"><figcaption><span>${escapeHTML(original.country)}</span><span>${escapeHTML(original.category)}</span></figcaption></figure>
      <section class="term-picker" id="termini" aria-labelledby="term-picker-title">
        <div class="term-picker-heading"><h2 id="term-picker-title">Odaberite termin</h2><span>${year}.</span></div>
        <p class="term-demo-note">Sedam dodatnih termina i cijena su ogledni. Svi termini su prošli i služe prezentaciji.</p>
        <p class="term-shared">${escapeHTML(original.duration)} <span>·</span> Polazak: ${escapeHTML(original.departure)} <span>·</span> po osobi</p>
        <div class="term-options" role="radiogroup" aria-label="Termini i cijene putovanja">${dates.map(d=>termButton(d,t,cheapest)).join('')}</div>
      </section>
    </div>
    <div class="selected-term-summary" aria-label="Odabrani termin"><div><span>Odabrani termin</span><strong id="selected-term-date">${compactRange(t)} ${year}.</strong></div><div class="selected-term-amount"><strong id="selected-term-price">${money(t.price)} <span>KM</span></strong><span id="selected-term-kind">${t.illustrative?'Ogledna cijena':'Izvorna arhivska cijena'} po osobi</span></div><p>Arhivski prikaz.<br>Za aktualne termine nazovite agenciju.</p><a class="text-link" href="tel:+38762102006">062 102 006 ${icon('right')}</a></div>
    <p class="term-source-note" id="term-source-note"></p>
    <p class="sr-only" id="term-selection-status" role="status" aria-live="polite" aria-atomic="true"></p>
    <div class="detail-grid"><div class="detail-content"><h2>Vaše putovanje, dan po dan.</h2>${original.program.split('\n').filter(Boolean).map((line,i)=>{const [title,...rest]=line.split('|');return `<article class="itinerary"><h3>${i+1}. ${escapeHTML(title)}</h3><p>${escapeHTML(rest.join('|'))}</p></article>`;}).join('')}<h2>Što je uključeno?</h2><ul class="included">${list(original.included)}</ul><h2>Dodatni troškovi</h2><ul class="included">${list(original.excluded)}</ul></div><aside class="booking" aria-label="Sažetak odabranog termina"><div class="price"><small id="term-booking-label"></small><strong id="term-booking-price"></strong></div><dl><div><dt>Polazak</dt><dd id="term-booking-start"></dd></div><div><dt>Povratak</dt><dd id="term-booking-end"></dd></div><div><dt>Trajanje</dt><dd>${escapeHTML(original.duration)}</dd></div><div><dt>Polazište</dt><dd>${escapeHTML(original.departure)}</dd></div></dl><p>Ovaj prikaz služi prezentaciji. Termini nisu dostupni za rezervaciju.</p><a class="direct-call" href="tel:+38762102006">Nazovite nas: <strong>062 102 006</strong></a></aside></div>
  </div>${contact()}`;

  const group=document.querySelector('.term-options');
  function select(id,announce=false) {
    const next=dates.find(d=>d.id===id);
    if(!next)return;
    const pagePosition={left:window.scrollX,top:window.scrollY};
    group.querySelectorAll('[data-term-id]').forEach(button=>{
      const selected=button.dataset.termId===id;
      button.setAttribute('aria-checked',String(selected));
      button.tabIndex=selected?0:-1;
    });
    document.getElementById('selected-term-date').textContent=`${compactRange(next)} ${next.date.slice(0,4)}.`;
    document.getElementById('selected-term-price').innerHTML=`${money(next.price)} <span>KM</span>`;
    document.getElementById('selected-term-kind').textContent=`${next.illustrative?'Ogledna cijena':'Izvorna arhivska cijena'} po osobi`;
    document.getElementById('term-booking-label').textContent=next.illustrative?'Ogledna cijena po osobi':'Cijena iz izvorne ponude';
    document.getElementById('term-booking-price').innerHTML=`${money(next.price)} <span>KM</span>`;
    document.getElementById('term-booking-start').textContent=fmt(next.date);
    document.getElementById('term-booking-end').textContent=fmt(next.end);
    document.getElementById('term-source-note').innerHTML=next.illustrative?'Odabrani termin i cijena su ogledni; zajednički program preuzet je iz izvorne ponude.':`Odabran je izvorni arhivski termin. <a href="${escapeHTML(next.source)}" target="_blank" rel="noopener">Pogledajte izvornu ponudu</a>.`;
    if(announce){
      history.replaceState(null,'','#putovanje/'+next.id);
      window.scrollTo({...pagePosition,behavior:'instant'});
      document.getElementById('term-selection-status').textContent=`Odabran termin ${fmt(next.date)} do ${fmt(next.end)}. ${money(next.price)} KM po osobi. ${next.illustrative?'Ogledni termin.':'Izvorni arhivski termin.'}`;
    }
  }
  group.onclick=e=>{
    const button=e.target.closest('[data-term-id]');
    if(button)select(button.dataset.termId,true);
  };
  group.onkeydown=e=>{
    if(!['ArrowLeft','ArrowRight','ArrowUp','ArrowDown','Home','End'].includes(e.key))return;
    e.preventDefault();
    const current=dates.findIndex(d=>d.id===e.target.closest('[data-term-id]')?.dataset.termId);
    const index=e.key==='Home'?0:e.key==='End'?dates.length-1:(current+(['ArrowLeft','ArrowUp'].includes(e.key)?-1:1)+dates.length)%dates.length;
    select(dates[index].id,true);
    group.querySelector(`[data-term-id="${dates[index].id}"]`).focus({preventScroll:true});
  };
  document.querySelector('.detail-from a').onclick=e=>{
    e.preventDefault();
    document.getElementById('termini').scrollIntoView({behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth'});
  };
  select(t.id);
}
