(function () {
  var btn = document.querySelector('.nav-toggle');
  var nav = document.querySelector('.nav');
  if (btn && nav) {
    btn.addEventListener('click', function () {
      var open = nav.classList.toggle('is-open');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      btn.textContent = open ? 'Close' : 'Menu';
    });
    nav.addEventListener('click', function (event) {
      if (event.target.closest('a')) {
        nav.classList.remove('is-open');
        btn.setAttribute('aria-expanded', 'false');
        btn.textContent = 'Menu';
      }
    });
  }

  var bar = document.querySelector('.mobile-bar');
  if (bar) {
    document.addEventListener('focusin', function (event) {
      if (event.target.closest('input, textarea, select')) {
        bar.hidden = true;
      }
    });
    document.addEventListener('focusout', function () {
      window.setTimeout(function () {
        var active = document.activeElement;
        if (!active || !active.closest || !active.closest('input, textarea, select')) {
          bar.hidden = false;
        }
      }, 50);
    });
  }

  var slides = document.querySelectorAll('.hero-photo img');
  var dots = document.querySelectorAll('.hero-dots button');
  var prev = document.querySelector('.hero-prev');
  var next = document.querySelector('.hero-next');
  if (slides.length > 1 && dots.length === slides.length) {
    var index = 0;
    var timer = 0;
    var wide = window.matchMedia('(min-width: 800px)');
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)');

    function show(nextIndex) {
      index = (nextIndex + slides.length) % slides.length;
      slides.forEach(function (slide, i) {
        slide.classList.toggle('is-on', i === index);
      });
      dots.forEach(function (dot, i) {
        dot.classList.toggle('is-on', i === index);
      });
    }

    function stop() {
      window.clearInterval(timer);
      timer = 0;
    }

    function start() {
      stop();
      if (!wide.matches || reduce.matches || document.hidden) {
        return;
      }
      timer = window.setInterval(function () {
        show(index + 1);
      }, 5000);
    }

    dots.forEach(function (dot, i) {
      dot.addEventListener('click', function () {
        show(i);
        start();
      });
    });
    if (prev) {
      prev.addEventListener('click', function () {
        show(index - 1);
        start();
      });
    }
    if (next) {
      next.addEventListener('click', function () {
        show(index + 1);
        start();
      });
    }
    wide.addEventListener('change', start);
    document.addEventListener('visibilitychange', start);
    start();
  }

  document.addEventListener('click', function (event) {
    var link = event.target.closest('[data-join-message]');
    if (!link) {
      return;
    }
    var type = document.getElementById('type');
    var date = document.getElementById('date');
    var message = document.getElementById('message');
    if (type) {
      type.value = link.getAttribute('data-join-type') || '';
    }
    if (date) {
      date.value = link.getAttribute('data-join-date') || '';
    }
    if (message) {
      message.value = link.getAttribute('data-join-message') || '';
    }
  });

  document.addEventListener('submit', function (event) {
    var form = event.target;
    var message = form.getAttribute('data-confirm');
    if (message && !window.confirm(message)) {
      event.preventDefault();
    }
  });
})();
