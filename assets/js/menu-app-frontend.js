(function(){
  // Idle timer state (simplified for single image)
  var idleTimer = null;
  var promoShown = false;
  

  
  function resetIdleTimer() {
    if (idleTimer) clearTimeout(idleTimer);
    
    if (promoShown) {
      hidePromo();
    }
    
    if (window.smdpPromo && Array.isArray(window.smdpPromo.images) && window.smdpPromo.images.length > 0 && window.smdpPromo.timeout) {
      idleTimer = setTimeout(showPromo, window.smdpPromo.timeout);
    }
  }
  window.resetIdleTimer = resetIdleTimer;
  function enterFullscreen() {
    var elem = document.documentElement;
    
    if (elem.requestFullscreen) {
      elem.requestFullscreen().catch(function(err) {
        // Fullscreen request failed silently
      });
    } else if (elem.webkitRequestFullscreen) {
      elem.webkitRequestFullscreen();
    } else if (elem.webkitEnterFullscreen) {
      elem.webkitEnterFullscreen();
    } else if (elem.mozRequestFullScreen) {
      elem.mozRequestFullScreen();
    } else if (elem.msRequestFullscreen) {
      elem.msRequestFullscreen();
    }
  }
  
  function exitFullscreen() {
    if (document.exitFullscreen) {
      document.exitFullscreen().catch(function(){});
    } else if (document.webkitExitFullscreen) {
      document.webkitExitFullscreen();
    } else if (document.webkitCancelFullScreen) {
      document.webkitCancelFullScreen();
    } else if (document.mozCancelFullScreen) {
      document.mozCancelFullScreen();
    } else if (document.msExitFullscreen) {
      document.msExitFullscreen();
    }
  }
  
  // Slideshow functions removed - single image only
  
  function showPromo() {
    var promo = document.getElementById('smdp-promo-screen');
    if (!promo) return;

    // Close item detail modal if open
    var itemModal = document.getElementById('smdp-item-modal');
    if (itemModal && window.jQuery) {
      window.jQuery('#smdp-item-modal').fadeOut(300, function() {
        window.jQuery('body').removeClass('smdp-modal-open');
      });
    }
    
    // Reset to first category
    var menuApp = document.querySelector('.smdp-menu-app-fe');
    if (menuApp) {
      var firstButton = menuApp.querySelector('.smdp-cat-btn');
      if (firstButton) {
        var firstSlug = firstButton.getAttribute('data-slug');
        
        var allButtons = menuApp.querySelectorAll('.smdp-cat-btn');
        allButtons.forEach(function(btn) {
          btn.classList.remove('active');
          btn.setAttribute('aria-selected', 'false');
        });
        
        firstButton.classList.add('active');
        firstButton.setAttribute('aria-selected', 'true');
        
        var allSections = menuApp.querySelectorAll('.smdp-app-section');
        allSections.forEach(function(sec) {
          sec.style.display = 'none';
          sec.style.opacity = '';
          sec.style.transform = '';
        });
        
        var firstSection = menuApp.querySelector('.smdp-app-section[data-slug="' + firstSlug + '"]');
        if (firstSection) {
          firstSection.style.display = 'block';
        }
      }
    }
    
    promoShown = true;

    // Get first (and only) slide
    var slide = promo.querySelector('.smdp-promo-slide');
    if (!slide) return;

    document.body.style.overflow = 'hidden';
    document.documentElement.style.overflow = 'hidden';

    var vw = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
    var vh = Math.max(document.documentElement.clientHeight || 0, window.innerHeight || 0);

    promo.style.display = 'block';
    promo.style.position = 'fixed';
    promo.style.top = '0px';
    promo.style.left = '0px';
    promo.style.right = '0px';
    promo.style.bottom = '0px';
    promo.style.width = vw + 'px';
    promo.style.height = vh + 'px';
    promo.style.zIndex = '2147483647';
    promo.style.transform = 'translateY(-100%)';
    promo.style.transition = 'transform 0.8s cubic-bezier(0.34, 1.56, 0.64, 1)';

    // Show single slide
    slide.style.width = vw + 'px';
    slide.style.height = vh + 'px';
    slide.style.display = 'block';
    slide.style.opacity = '1';

    promo.offsetHeight;

    setTimeout(function() {
      promo.style.transform = 'translateY(0)';
    }, 50)
    
    setTimeout(function() {
      enterFullscreen();
    }, 900);
  }
	window.showPromo = showPromo;

  function hidePromo() {
    var promo = document.getElementById('smdp-promo-screen');
    if (!promo) return;

    promoShown = false;

    promo.style.transition = 'transform 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
    promo.style.transform = 'translateY(-100%)';

    setTimeout(function() {
      promo.style.display = 'none';
      promo.style.transform = '';
      promo.style.transition = '';

      // Hide the single slide
      var slide = promo.querySelector('.smdp-promo-slide');
      if (slide) {
        slide.style.display = 'none';
        slide.style.opacity = '';
      }

      document.body.style.overflow = '';
      document.documentElement.style.overflow = '';

      exitFullscreen();

      // Call refresh function when promo is dismissed
      if (typeof window.smdpRefreshOnPromoDismiss === 'function') {
        window.smdpRefreshOnPromoDismiss();
      }
    }, 650);
  }
 

  function setupIdleDetection() {
    var events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'touchmove', 'click'];
    events.forEach(function(event) {
      document.addEventListener(event, resetIdleTimer, true);
    });
    
    var promo = document.getElementById('smdp-promo-screen');
    if (promo) {
      promo.addEventListener('click', function(e) {
        e.stopPropagation();
        e.preventDefault();
        resetIdleTimer();
      });
      
      promo.addEventListener('touchstart', function(e) {
        e.stopPropagation();
        e.preventDefault();
        resetIdleTimer();
      });
    }
    
    window.addEventListener('resize', function() {
      if (promoShown) {
        var promo = document.getElementById('smdp-promo-screen');
        var slide = promo ? promo.querySelector('.smdp-promo-slide') : null;
        if (promo && slide) {
          var vw = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
          var vh = Math.max(document.documentElement.clientHeight || 0, window.innerHeight || 0);

          promo.style.width = vw + 'px';
          promo.style.height = vh + 'px';
          slide.style.width = vw + 'px';
          slide.style.height = vh + 'px';
        }
      }
    });

    window.addEventListener('orientationchange', function() {
      if (promoShown) {
        setTimeout(function() {
          var promo = document.getElementById('smdp-promo-screen');
          var slide = promo ? promo.querySelector('.smdp-promo-slide') : null;
          if (promo && slide) {
            var vw = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
            var vh = Math.max(document.documentElement.clientHeight || 0, window.innerHeight || 0);

            promo.style.width = vw + 'px';
            promo.style.height = vh + 'px';
            slide.style.width = vw + 'px';
            slide.style.height = vh + 'px';
          }
        }, 100);
      }
    });
  }
  
  function supportsSticky() {
    var div = document.createElement('div');
    div.style.position = 'sticky';
    return div.style.position === 'sticky';
  }

  function stickyFallback(root) {
    var sidebar = root.querySelector('.smdp-cat-sidebar');
    var content = root.querySelector('.smdp-cat-main');
    if (!sidebar || !content) return;
    
    var sidebarTop = sidebar.offsetTop;
    
    window.addEventListener('scroll', function() {
      var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
      if (scrollTop > sidebarTop) {
        sidebar.style.position = 'fixed';
        sidebar.style.top = '0';
      } else {
        sidebar.style.position = '';
        sidebar.style.top = '';
      }
    });
  }

  function scrollToSectionTop(root, targetSection) {
    if (!targetSection) return;
    var main = root.querySelector('.smdp-cat-main');
    if (main) {
      main.scrollTop = 0;
    }
  }

  function setup(root) {
    var buttons = root.querySelectorAll('.smdp-cat-btn');
    var sections = root.querySelectorAll('.smdp-app-section');
    
    function show(slug, skipRefresh) {
      var found = false;
      sections.forEach(function(sec) {
        var on = (sec.getAttribute('data-slug') === slug);
        sec.style.display = on ? 'block' : 'none';
        if (on) found = true;
      });
      buttons.forEach(function(btn) {
        var s = btn.getAttribute('data-slug');
        var active = (s === slug);
        btn.classList.toggle('active', active);
        btn.setAttribute('aria-selected', active ? 'true' : 'false');
      });
      var targetSection = null;
      sections.forEach(function(sec) {
        if (sec.getAttribute('data-slug') === slug) { targetSection = sec; }
      });
      if (!targetSection && sections[0]) {
        targetSection = sections[0];
        targetSection.style.display = 'block';
      }
      scrollToSectionTop(root, targetSection);

      // Trigger refresh for this category's menu container (unless it's the initial load)
      if (!skipRefresh && targetSection && window.smdpRefreshMenu && typeof jQuery !== 'undefined') {
        var container = targetSection.querySelector('.smdp-menu-container');
        if (container) {
          window.smdpRefreshMenu(jQuery(container));
        }
      }
    }

    var initial = buttons[0].getAttribute('data-slug');
    buttons.forEach(function(btn) {
      btn.addEventListener('click', function(e) {
        e.preventDefault();
        show(btn.getAttribute('data-slug')); // Will refresh on click
      });
    });
    show(initial, true); // Skip refresh on initial load - refresh.js handles it
    
    try {
      if (root.getAttribute('data-promo-enabled') === '1' && 
          window.smdpPromo && 
          Array.isArray(window.smdpPromo.images) && 
          window.smdpPromo.images.length > 0) {
        setupIdleDetection();
      }
    } catch(e) {
      // Promo setup skipped
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      document.querySelectorAll('.smdp-menu-app-fe').forEach(function(root) {
        setup(root);
        if (root.classList.contains('layout-left') && !supportsSticky()) {
          stickyFallback(root);
        }
      });
    });
  } else {
    document.querySelectorAll('.smdp-menu-app-fe').forEach(function(root) {
      setup(root);
      if (root.classList.contains('layout-left') && !supportsSticky()) {
        stickyFallback(root);
      }
    });
  }
})();
