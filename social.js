'use strict';

// Temporary, user-approved test contact. Replace with the agency's confirmed number before publication.
const SOCIAL_CONFIG = {
  whatsappNumber: '38763169051',
  whatsappDemo: true,
  instagramProfile: 'https://www.instagram.com/maestro.travel.orasje/',
  posts: [
    {id:'De2C00fFw_8',image:'assets/instagram-italija.webp',title:'Shopping u Italiji',description:'Palmanova, Noventa di Piave i šetnja Trstom.',date:'2026-10-23',dateLabel:'23.10.2026.',alt:'Maestro Travel: Italija i Trst, Palmanova i Noventa di Piave, autobusom 4.–6. prosinca 2026. Cijena aranžmana 245 KM, hotel 4 zvjezdice s doručkom.'},
    {id:'DekIIs0DavH',image:'assets/instagram-opatija.webp',title:'Opatija i Festival čokolade',description:'Jednodnevni izlet, slatki užici i šetnja uz more.',date:'2026-10-16',dateLabel:'16.10.2026.',alt:'Maestro Travel: Festival čokolade u Opatiji, jednodnevni izlet autobusom 7. studenoga 2026. Cijena 79 KM po osobi, šetnja Opatijom i posjet festivalu.'},
    {id:'Dei8l5mj3HA',image:'assets/instagram-interliber.webp',title:'Interliber u Zagrebu',description:'Jedan dan za knjige, nove priče i istraživanje Zagreba.',date:'2026-10-16',dateLabel:'16.10.2026.',alt:'Maestro Travel: sajam knjiga Interliber i Zagreb, jednodnevni izlet autobusom 14. studenoga 2026. Cijena 59 KM po osobi, posjet sajmu i slobodno vrijeme u Zagrebu.'}
  ]
};

function socialIcon(name) {
  if(name==='instagram')return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r=".9" fill="currentColor" stroke="none"/></svg>';
  return '<svg class="icon wa-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.5 11.7a8.5 8.5 0 0 1-12.6 7.5L3 20.5l1.3-4.8a8.5 8.5 0 1 1 16.2-4Z"/><path d="m8 7.4 1.6 2.7-1 1.2a7.9 7.9 0 0 0 4.1 4.1l1.2-1 2.7 1.6c-.4 1.5-1.5 2-3 1.5-3.7-1.2-6.4-3.9-7.5-7.5-.5-1.3.2-2.3 1.9-2.6Z"/></svg>';
}

function whatsappMessage(trip) {
  const context=trip?`putovanje ${trip.title}, termin ${fmt(trip.date)} – ${fmt(trip.end).replace(/\.$/,'')}`:'ponuda putovanja agencije Maestro Travel';
  return `Pozdrav! Zanima me ${context}. Možete li mi poslati više informacija?${SOCIAL_CONFIG.whatsappDemo?' (Upit iz demonstracijskog prikaza.)':''}`;
}

function whatsappUrl(trip) {
  return `https://wa.me/${SOCIAL_CONFIG.whatsappNumber}?text=${encodeURIComponent(whatsappMessage(trip))}`;
}

function whatsappAction(trip) {
  return `<div class="wa-context"><a class="wa-action" data-whatsapp-context href="${escapeHTML(whatsappUrl(trip))}" target="_blank" rel="noopener noreferrer">${socialIcon('whatsapp')}<span>Upit preko WhatsAppa</span>${icon('right')}</a>${SOCIAL_CONFIG.whatsappDemo?'<small>Demo kontakt · poruku šaljete testnom broju.</small>':''}</div>`;
}

function instagramSection() {
  const posts=SOCIAL_CONFIG.posts.slice(0,3);
  if(!posts.length)return '';
  return `<section class="container instagram-section" id="instagram" aria-labelledby="instagram-title">
    <div class="instagram-heading"><div><h2 id="instagram-title">S našeg <em>Instagrama.</em></h2><p>Odabrane objave, nove ideje za putovanja.</p></div><a class="instagram-profile" href="${SOCIAL_CONFIG.instagramProfile}" target="_blank" rel="noopener noreferrer">${socialIcon('instagram')}<span>@maestro.travel.orasje</span>${icon('diagonal')}</a></div>
    <div class="instagram-posts" id="instagram-posts" aria-label="Odabrane Instagram objave">${posts.map((post,i)=>`<article class="instagram-post" aria-label="Objava ${i+1} od ${posts.length}: ${escapeHTML(post.title)}"><a class="instagram-media" href="https://www.instagram.com/p/${post.id}/" target="_blank" rel="noopener noreferrer" aria-describedby="instagram-open-${post.id}"><img src="${post.image}" alt="${escapeHTML(post.alt)}" loading="lazy" decoding="async" width="1254" height="1254"><span class="instagram-media-fallback" hidden>Fotografija trenutačno nije dostupna.<br>Otvorite objavu na Instagramu.</span><span class="sr-only" id="instagram-open-${post.id}">Otvara izvornu objavu na Instagramu.</span><span class="instagram-media-link">${socialIcon('instagram')}</span></a><div class="instagram-post-copy"><time datetime="${post.date}">${post.dateLabel}</time><h3><a href="https://www.instagram.com/p/${post.id}/" target="_blank" rel="noopener noreferrer">${escapeHTML(post.title)}</a></h3><p>${escapeHTML(post.description)}</p><a class="instagram-post-link" href="https://www.instagram.com/p/${post.id}/" target="_blank" rel="noopener noreferrer">Pogledaj objavu ${icon('diagonal')}</a></div></article>`).join('')}</div>
    <div class="instagram-navigation" ${posts.length<2?'hidden':''}><span id="instagram-counter" role="status" aria-live="polite" aria-atomic="true">Objava 1 od ${posts.length}</span><div><button type="button" id="instagram-prev" aria-label="Prethodna Instagram objava" disabled>${icon('left')}</button><button type="button" id="instagram-next" aria-label="Sljedeća Instagram objava">${icon('right')}</button></div></div>
  </section>`;
}

let whatsappObserver;
function updateWhatsApp(trip) {
  let floating=document.getElementById('whatsapp-floating');
  if(!floating){
    floating=document.createElement('a');
    floating.id='whatsapp-floating';
    floating.className='wa-floating';
    floating.target='_blank';
    floating.rel='noopener noreferrer';
    floating.innerHTML=`${socialIcon('whatsapp')}<span class="wa-floating-copy">WhatsApp${SOCIAL_CONFIG.whatsappDemo?'<small>Demo kontakt</small>':''}</span>${SOCIAL_CONFIG.whatsappDemo?'<span class="wa-mobile-demo">Demo</span>':''}`;
    document.body.append(floating);
  }
  const isAdmin=location.hash.startsWith('#admin');
  floating.hidden=isAdmin;
  floating.href=whatsappUrl(trip);
  floating.setAttribute('aria-label',`Otvori WhatsApp${SOCIAL_CONFIG.whatsappDemo?' — demo kontakt':''}${trip?': '+trip.title:''}`);
  document.body.classList.toggle('has-whatsapp',!isAdmin);
  document.querySelectorAll('[data-whatsapp-context]').forEach(a=>a.href=whatsappUrl(trip));
  if(whatsappObserver)whatsappObserver.disconnect();
  floating.classList.remove('is-redundant');
  const visible=new Set();
  if(!isAdmin&&typeof IntersectionObserver!=='undefined'){
    whatsappObserver=new IntersectionObserver(entries=>{
      entries.forEach(entry=>entry.intersectionRatio>=.6?visible.add(entry.target):visible.delete(entry.target));
      floating.classList.toggle('is-redundant',visible.size>0&&document.activeElement!==floating);
    },{threshold:[0,.6,1]});
    document.querySelectorAll('[data-whatsapp-context]').forEach(a=>whatsappObserver.observe(a));
  }
}

function initSocial(trip) {
  updateWhatsApp(trip);
  const list=document.getElementById('instagram-posts');
  if(!list)return;
  const posts=[...list.children];
  let current=0;
  let pending=null,settleTimer;
  const prev=document.getElementById('instagram-prev');
  const next=document.getElementById('instagram-next');
  const counter=document.getElementById('instagram-counter');
  function update(index){
    current=Math.max(0,Math.min(posts.length-1,index));
    prev.disabled=current===0;
    next.disabled=current===posts.length-1;
    counter.textContent=`Objava ${current+1} od ${posts.length}`;
  }
  function go(offset){
    const target=Math.max(0,Math.min(posts.length-1,(pending??current)+offset));
    pending=target;
    list.scrollTo({left:posts[target].offsetLeft-posts[0].offsetLeft,behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth'});
    update(target);
  }
  prev.onclick=()=>go(-1);
  next.onclick=()=>go(1);
  list.addEventListener('scroll',()=>{
    const step=posts.length>1?posts[1].offsetLeft-posts[0].offsetLeft:0;
    if(!step)return;
    if(pending===null)update(Math.round(list.scrollLeft/step));
    clearTimeout(settleTimer);
    settleTimer=setTimeout(()=>{pending=null;update(Math.round(list.scrollLeft/step));},120);
  },{passive:true});
  list.addEventListener('pointerdown',()=>{pending=null;},{passive:true});
  list.querySelectorAll('img').forEach(img=>img.addEventListener('error',()=>{
    img.hidden=true;
    img.parentElement.classList.add('is-unavailable');
    img.parentElement.querySelector('.instagram-media-fallback').hidden=false;
  },{once:true}));
}
