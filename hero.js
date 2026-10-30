'use strict';
const FEATURED_IDS = ['toskana', 'istanbul', 'andaluzija'];
let featuredIndex = 0;

function featuredTrips() {
  return FEATURED_IDS.map(id => trips.find(t => t.id === id && t.visible)).filter(Boolean).map(lowestOffer).slice(0, 3);
}

function heroMarkup() {
  const items = featuredTrips();
  if (!items.length) return '<section class="container featured-empty"><h1>Pronađite svoje sljedeće putovanje.</h1><a class="btn" href="#putovanja">Pogledaj ponude ↗</a></section>';
  featuredIndex = Math.min(featuredIndex, items.length - 1);
  const t = items[featuredIndex];
  return `<section class="destination-hero" aria-label="Izdvojena putovanja" aria-roledescription="karusel">
    <div class="destination-backgrounds" aria-hidden="true">${items.map((item,i) => `<img class="destination-background ${i===featuredIndex?'is-active':''}" src="${safeImage(item.image)}" alt="" width="1800" height="1100" ${i===featuredIndex?'fetchpriority="high"':''} data-background="${i}">`).join('')}</div>
    <div class="destination-shade" aria-hidden="true"></div>
    <div class="destination-inner">
      <div class="destination-heading"><span class="destination-kicker">Izdvojena putovanja</span><span class="destination-agency">MAESTRO TRAVEL · ORAŠJE</span></div>
      <div class="destination-body">
        <div class="destination-copy" id="destination-copy">
          <p class="destination-country" id="destination-country"></p>
          <h1 class="destination-title" id="destination-title"></h1>
          <p class="destination-description" id="destination-description"></p>
          <div class="destination-facts" id="destination-facts"></div>
          <div class="destination-actions"><a class="btn yellow" id="destination-link" href="#putovanje/${t.id}">Termini i detalji ${icon('right')}</a><div class="destination-price"><small>Već od</small><strong id="destination-price"></strong></div></div>
        </div>
        <div class="destination-others" ${items.length<2?'hidden':''}><p>JOŠ IZ PONUDE</p><div class="destination-cards" id="destination-cards"></div></div>
      </div>
      <div class="destination-bottom"><div class="destination-navigation" ${items.length<2?'hidden':''}><button type="button" class="destination-arrow" id="destination-prev" aria-label="Prethodno izdvojeno putovanje">${icon('left')}</button><button type="button" class="destination-arrow" id="destination-next" aria-label="Sljedeće izdvojeno putovanje">${icon('right')}</button><span id="destination-counter" aria-hidden="true"></span></div><a class="destination-all" href="#putovanja">Sva putovanja ${icon('right')}</a></div>
    </div>
    <p class="sr-only" id="destination-status" role="status" aria-live="polite" aria-atomic="true"></p>
  </section><div class="container destination-reference"><span id="destination-reference-note">Arhivske ponude i ogledni termini iz 2026.</span><a id="destination-source" href="${escapeHTML(t.source)}" target="_blank" rel="noopener">Izvorna ponuda</a></div>`;
}

function initHero() {
  const root = document.querySelector('.destination-hero');
  if (!root) return;
  const items = featuredTrips();
  function update(announce = false) {
    const t = items[featuredIndex];
    root.querySelectorAll('[data-background]').forEach(img => img.classList.toggle('is-active', Number(img.dataset.background) === featuredIndex));
    document.getElementById('destination-country').textContent = `${t.country} / ${t.category}`;
    document.getElementById('destination-title').textContent = t.title;
    document.getElementById('destination-description').textContent = t.description;
    document.getElementById('destination-facts').innerHTML = `<span>${dateLabel(t)}</span><span>${escapeHTML(t.duration)}</span><span>Polazak: ${escapeHTML(t.departure)}</span>`;
    document.getElementById('destination-price').innerHTML = `${money(t.price)} <span>KM</span>`;
    document.getElementById('destination-link').href = '#putovanje/' + t.id;
    const source=document.getElementById('destination-source');
    source.hidden=Boolean(t.illustrative)||!t.source;
    if(!source.hidden)source.href=t.source;else source.removeAttribute('href');
    document.getElementById('destination-reference-note').textContent=t.illustrative?'Istanbul: sedam dodatnih termina i cijena služe demonstraciji.':'Primjeri s postojeće stranice · arhivski termini iz 2026.';
    document.getElementById('destination-counter').innerHTML = `<strong>${String(featuredIndex + 1).padStart(2,'0')}</strong><span>/ ${String(items.length).padStart(2,'0')}</span>`;
    document.getElementById('destination-cards').innerHTML = items.map((other,i) => i===featuredIndex?'':`<button class="destination-card" type="button" data-feature-index="${i}" aria-label="Izdvoji putovanje: ${escapeHTML(other.title)}"><img src="${safeImage(other.image)}" alt="" width="400" height="600"><span class="destination-card-copy"><small>${escapeHTML(other.country)}</small><strong>${escapeHTML(other.title)}</strong><span class="destination-card-price">od ${money(other.price)} KM</span><span class="destination-card-action">Prikaži destinaciju ${icon('right')}</span></span></button>`).join('');
    if (announce) {
      document.getElementById('destination-status').textContent = `${featuredIndex + 1}. od ${items.length}: ${t.title}. ${fmt(t.date)}. ${money(t.price)} KM. ${t.illustrative?'Ogledni termin.':'Arhivska ponuda.'}`;
      const copy = document.getElementById('destination-copy');
      copy.classList.remove('destination-enter');
      void copy.offsetWidth;
      copy.classList.add('destination-enter');
    }
  }
  function step(offset) {
    featuredIndex = (featuredIndex + offset + items.length) % items.length;
    update(true);
  }
  document.getElementById('destination-prev').onclick = () => step(-1);
  document.getElementById('destination-next').onclick = () => step(1);
  document.getElementById('destination-cards').onclick = e => {
    const button = e.target.closest('[data-feature-index]');
    if (!button) return;
    featuredIndex = Number(button.dataset.featureIndex);
    update(true);
    document.getElementById('destination-next').focus({preventScroll:true});
  };
  root.addEventListener('keydown', e => {
    if (e.altKey || e.ctrlKey || e.metaKey || !['ArrowLeft','ArrowRight'].includes(e.key)) return;
    e.preventDefault();
    step(e.key==='ArrowRight'?1:-1);
    document.getElementById('destination-next').focus({preventScroll:true});
  });
  update();
}
