// LORENCE -- light progressive enhancement: scroll reveals + pointer tilt.
// Everything degrades to a fully usable page if this file fails to load.

(function () {
  'use strict';

  document.querySelectorAll('input[name="guests"][type="number"], input[name="children"][type="number"], input[name="seniors"][type="number"]').forEach(function (input) {
    var select = document.createElement('select');
    select.name = input.name; select.required = input.required; select.className = input.className;
    var start = input.name === 'guests' ? 1 : 0;
    for (var n = start; n <= 10; n++) { var option = document.createElement('option'); option.value = n; option.textContent = n + ' ' + (input.name === 'guests' ? (n === 1 ? 'adult' : 'adults') : (input.name === 'children' ? 'child' + (n === 1 ? '' : 'ren') : 'senior' + (n === 1 ? '' : 's'))); if (n === Number(input.value)) option.selected = true; select.appendChild(option); }
    input.replaceWith(select);
  });

  document.querySelectorAll('select[name="guests"], select[name="children"], select[name="seniors"]').forEach(function (select) {
    if (select.closest('.guest-picker')) return;
    var container = select.parentNode;
    var wrap = document.createElement('div'); wrap.className = 'guest-picker';
    var button = document.createElement('button'); button.type = 'button'; button.className = 'guest-picker-button';
    var menu = document.createElement('div'); menu.className = 'guest-picker-menu';
    function label() { return select.options[select.selectedIndex].textContent; }
    button.textContent = label();
    Array.prototype.forEach.call(select.options, function (option) {
      var item = document.createElement('button'); item.type = 'button'; item.className = 'guest-picker-option'; item.textContent = option.textContent; item.dataset.value = option.value;
      item.addEventListener('click', function () { select.value = item.dataset.value; button.textContent = label(); wrap.classList.remove('open'); select.dispatchEvent(new Event('change', {bubbles:true})); });
      menu.appendChild(item);
    });
    button.addEventListener('click', function () { wrap.classList.toggle('open'); });
    wrap.appendChild(select); wrap.appendChild(button); wrap.appendChild(menu); select.tabIndex = -1; select.setAttribute('aria-hidden','true'); wrap.addEventListener('click', function(e){e.stopPropagation();}); container.replaceWith(wrap);
  });
  document.addEventListener('click', function () { document.querySelectorAll('.guest-picker.open').forEach(function (el) { el.classList.remove('open'); }); });

  document.querySelectorAll('input[type="date"]').forEach(function (input) {
    var container = input.parentNode;
    var wrap = document.createElement('div'); wrap.className = 'date-picker';
    var display = document.createElement('input'); display.type = 'text'; display.className = 'date-display'; display.readOnly = true; display.required = input.required; display.placeholder = 'Select date'; display.setAttribute('aria-label', input.name);
    var calendar = document.createElement('div'); calendar.className = 'date-calendar';
    var value = input.value || input.min || ''; var cursor = value ? new Date(value + 'T12:00:00') : new Date();
    function iso(date){return date.getFullYear()+'-'+String(date.getMonth()+1).padStart(2,'0')+'-'+String(date.getDate()).padStart(2,'0');}
    function pretty(date){return date.toLocaleDateString(undefined,{month:'short',day:'numeric',year:'numeric'});}
    function render(){ var y=cursor.getFullYear(), m=cursor.getMonth(); calendar.innerHTML=''; var head=document.createElement('div'); head.className='date-calendar-head'; var title=document.createElement('b'); title.textContent=cursor.toLocaleDateString(undefined,{month:'long',year:'numeric'}); var prev=document.createElement('button');prev.type='button';prev.textContent='‹';var next=document.createElement('button');next.type='button';next.textContent='›';prev.onclick=function(e){e.stopPropagation();cursor.setMonth(m-1);render();};next.onclick=function(e){e.stopPropagation();cursor.setMonth(m+1);render();};head.append(prev,title,next);calendar.appendChild(head);var grid=document.createElement('div');grid.className='date-calendar-grid';['S','M','T','W','T','F','S'].forEach(function(d){var el=document.createElement('span');el.textContent=d;el.className='date-weekday';grid.appendChild(el);});var first=new Date(y,m,1).getDay();for(var blank=0;blank<first;blank++)grid.appendChild(document.createElement('span'));var days=new Date(y,m+1,0).getDate();for(var d=1;d<=days;d++){var date=new Date(y,m,d), b=document.createElement('button');b.type='button';b.textContent=d;b.className='date-day';var day=iso(date);if(input.min&&day<input.min)b.disabled=true;if(day===input.value)b.classList.add('selected');b.onclick=function(e){e.stopPropagation();var picked=new Date(y,m,Number(this.textContent));input.value=iso(picked);display.value=pretty(picked);display.dispatchEvent(new Event('change',{bubbles:true}));calendar.classList.remove('open');};grid.appendChild(b);}calendar.appendChild(grid);}
    if (value) display.value = pretty(new Date(value+'T12:00:00')); input.type='hidden'; input.required=false; wrap.appendChild(input);wrap.appendChild(display);wrap.appendChild(calendar);container.replaceWith(wrap);display.addEventListener('click',function(e){e.stopPropagation();document.querySelectorAll('.date-calendar.open').forEach(function(c){c.classList.remove('open');});cursor=input.value?new Date(input.value+'T12:00:00'):new Date();render();calendar.classList.add('open');});document.addEventListener('click',function(){calendar.classList.remove('open');});
  });
  var checkIn = document.querySelector('input[name="check_in"]');
  var checkOut = document.querySelector('input[name="check_out"]');
  if (checkIn && checkOut) {
    checkIn.addEventListener('change', function () {
      checkOut.min = checkIn.value || checkOut.min;
      if (checkOut.value && checkOut.value <= checkIn.value) checkOut.value = '';
    });
  }

  var navLinks = document.querySelectorAll('.nav nav a');
  var sections = document.querySelectorAll('[id="amenities"],[id="experience"],[id="contact"]');
  function setActive(key) { navLinks.forEach(function (link) { link.classList.toggle('active', link.dataset.nav === key); }); }
  if (location.hash && document.getElementById(location.hash.substring(1))) setActive(location.hash.substring(1));
  navLinks.forEach(function (link) { link.addEventListener('click', function () { var hash = link.hash; setActive(hash ? hash.substring(1) : (link.pathname.indexOf('rooms.php') >= 0 ? 'rooms' : 'home')); }); });
  if (navLinks.length) {
    if ('IntersectionObserver' in window) {
      var navObserver = new IntersectionObserver(function (entries) { entries.forEach(function (entry) { if (entry.isIntersecting) setActive(entry.target.id); }); }, {threshold: 0.32});
      sections.forEach(function (section) { navObserver.observe(section); });
    }
    window.addEventListener('scroll', function () { if (window.scrollY < 180) setActive('home'); }, {passive:true});
  }

  // ---------------------------------------------------------------
  // Reveal-on-scroll for elements with a .pane / section treatment.
  // ---------------------------------------------------------------
  var revealables = document.querySelectorAll(
    '.pane, .features > div, .amenities-grid li, .room-wide, .confirm-card'
  );

  if (revealables.length) {
    if (!('IntersectionObserver' in window)) return;

    document.documentElement.classList.add('js-reveal');

    var style = document.createElement('style');
    style.textContent =
      '.js-reveal .reveal-pending{opacity:0;transform:translateY(26px);' +
      'transition:opacity .7s ease,transform .7s cubic-bezier(.2,.8,.2,1)}' +
      '.js-reveal .reveal-in{opacity:1;transform:none}';
    document.head.appendChild(style);

    revealables.forEach(function (el) {
      el.classList.add('reveal-pending');
    });

    var io = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) return;
          var el = entry.target;
          var delay = Math.min(parseInt(el.style.getPropertyValue('--d'), 10) || 0, 400);
          el.style.transitionDelay = delay + 'ms';
          el.classList.add('reveal-in');
          io.unobserve(el);
        });
      },
      { threshold: 0.12, rootMargin: '0px 0px -40px 0px' }
    );

    revealables.forEach(function (el) { io.observe(el); });
  }

  // ---------------------------------------------------------------
  // Subtle 3D tilt on the floating hero panes (pointer devices only).
  // ---------------------------------------------------------------
  var stage = document.querySelector('.hero-stage');
  var panes = stage ? stage.querySelectorAll('.pane') : [];

  if (!panes.length || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;

  var frame = null;

  stage.addEventListener('pointermove', function (e) {
    if (frame) return;
    frame = requestAnimationFrame(function () {
      frame = null;
      var rect = stage.getBoundingClientRect();
      var px = (e.clientX - rect.left) / rect.width - 0.5;
      var py = (e.clientY - rect.top) / rect.height - 0.5;

      panes.forEach(function (pane) {
        // Depth scales how far the pane travels, so the stack reads as layers.
        var depth = parseFloat(pane.style.getPropertyValue('--d')) || 40;
        var depth01 = depth / 130;
        pane.style.transition = 'transform .12s linear, border-color .25s ease';
        pane.style.transform =
          'rotateY(' + (px * 7 * depth01).toFixed(2) + 'deg) ' +
          'rotateX(' + (-py * 5 * depth01).toFixed(2) + 'deg) ' +
          'translate3d(0,0,' + (depth01 * 40).toFixed(0) + 'px)';
      });
    });
  });

  function resetTilt() {
    panes.forEach(function (pane) {
      pane.style.transition = 'transform .5s cubic-bezier(.2,.8,.2,1), border-color .25s ease';
      pane.style.transform = '';
    });
  }

  stage.addEventListener('pointerleave', resetTilt);
  window.addEventListener('blur', resetTilt);
})();
