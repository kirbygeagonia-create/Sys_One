// Mobile nav toggle
document.addEventListener('DOMContentLoaded', function() {
    var toggle = document.getElementById('navToggle');
    var links = document.getElementById('navLinks');
    if (toggle && links) {
        toggle.addEventListener('click', function() {
            links.classList.toggle('active');
            var icon = toggle.querySelector('.fas');
            if (icon) {
                icon.className = links.classList.contains('active') ? 'fas fa-times' : 'fas fa-bars';
            }
        });
        links.querySelectorAll('a').forEach(function(link) {
            link.addEventListener('click', function() {
                links.classList.remove('active');
                var icon = toggle.querySelector('.fas');
                if (icon) icon.className = 'fas fa-bars';
            });
        });
    }
});

// Auto-hide alerts after 6 seconds + add close button
document.querySelectorAll('.alert').forEach(function(alert) {
    var btn = document.createElement('button');
    btn.className = 'alert-close';
    btn.innerHTML = '&times;';
    btn.setAttribute('aria-label', 'Close');
    btn.onclick = function() {
        alert.style.transition = 'opacity 0.3s';
        alert.style.opacity = '0';
        setTimeout(function() { alert.remove(); }, 300);
    };
    alert.appendChild(btn);

    setTimeout(function() {
        if (alert.parentElement) {
            alert.style.transition = 'opacity 0.5s, transform 0.5s';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(function() { if (alert.parentElement) alert.remove(); }, 500);
        }
    }, 6000);
});

// Toast notification helper
function showToast(message, type) {
    type = type || 'success';
    var toast = document.createElement('div');
    toast.className = 'toast toast-' + type;
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(function() {
        toast.style.transition = 'opacity 0.5s, transform 0.5s';
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(10px)';
        setTimeout(function() { toast.remove(); }, 500);
    }, 3500);
}

// Modal helpers
function openModal(id) {
    var modal = document.getElementById(id);
    if (modal) modal.classList.add('active');
}
function closeModal(id) {
    var modal = document.getElementById(id);
    if (modal) modal.classList.remove('active');
}

// Close modal on overlay click
document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) {
            overlay.classList.remove('active');
        }
    });
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(function(m) {
            m.classList.remove('active');
        });
    }
});

// Confirm dialog helper
function confirmAction(message, callback) {
    if (confirm(message)) callback();
}

// Load skills via AJAX (for modal forms and browse filter)
function loadSkills(categorySelectId, skillSelectId) {
    var cat = document.getElementById(categorySelectId);
    var skill = document.getElementById(skillSelectId);
    if (!cat || !skill) return;
    var categoryId = cat.value;
    if (!categoryId) {
        skill.innerHTML = '<option value="">' + (skillSelectId === 'skill_id' ? 'All Skills' : 'First select a category') + '</option>';
        skill.disabled = true;
        return;
    }
    skill.disabled = true;
    skill.innerHTML = '<option value="">Loading...</option>';
    fetch('/actions/get_skills.php?category_id=' + categoryId)
        .then(function(r) { return r.json(); })
        .then(function(skills) {
            skill.innerHTML = '<option value="">' + (skillSelectId === 'skill_id' ? 'All Skills' : 'Select a skill') + '</option>';
            skills.forEach(function(s) {
                var opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = s.name;
                skill.appendChild(opt);
            });
            skill.disabled = false;
        })
        .catch(function() {
            skill.innerHTML = '<option value="">Error loading skills</option>';
            skill.disabled = false;
        });
}

// Star rating highlight
function highlightStars(input) {
    if (!input) return;
    var value = parseInt(input.value);
    var container = input.closest('.star-rating') || input.parentElement.parentElement;
    container.querySelectorAll('.star').forEach(function(star) {
        var starVal = parseInt(star.dataset.value);
        star.style.color = starVal <= value ? '#f59e0b' : '#ddd';
    });
}

// Handle star rating via click on stars
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.star-rating .star').forEach(function(star) {
        star.addEventListener('click', function() {
            var value = parseInt(this.dataset.value);
            var radio = this.closest('.star-rating').querySelector('input[value="' + value + '"]');
            if (radio) {
                radio.checked = true;
                highlightStars(radio);
            }
        });
        star.addEventListener('mouseenter', function() {
            var value = parseInt(this.dataset.value);
            var container = this.closest('.star-rating');
            container.querySelectorAll('.star').forEach(function(s) {
                var sv = parseInt(s.dataset.value);
                s.style.color = sv <= value ? '#f59e0b' : '#ddd';
            });
        });
        star.addEventListener('mouseleave', function() {
            var container = this.closest('.star-rating');
            var checked = container.querySelector('input[type="radio"]:checked');
            if (checked) {
                highlightStars(checked);
            } else {
                container.querySelectorAll('.star').forEach(function(s) {
                    s.style.color = '#ddd';
                });
            }
        });
    });
});

// Back to top button
(function() {
    var btn = document.createElement('button');
    btn.id = 'backToTop';
    btn.innerHTML = '<i class="fas fa-arrow-up"></i>';
    btn.setAttribute('aria-label', 'Back to top');
    document.body.appendChild(btn);
    window.addEventListener('scroll', function() {
        btn.classList.toggle('visible', window.scrollY > 400);
    });
})();

// Password strength meter (shared by register.php and index.php modals)
function checkPasswordStrength(pwd, barId) {
    barId = barId || 'passwordStrength';
    var bar = document.getElementById(barId);
    if (!bar) return;
    bar.style.removeProperty('width');
    if (pwd.length === 0) { bar.className = 'password-strength'; return; }
    var score = 0;
    if (pwd.length >= 8) score++;
    if (/[A-Z]/.test(pwd)) score++;
    if (/[0-9]/.test(pwd)) score++;
    if (/[^A-Za-z0-9]/.test(pwd)) score++;
    bar.className = 'password-strength';
    if (score <= 1) { bar.classList.add('weak'); }
    else if (score <= 2) { bar.classList.add('medium'); }
    else { bar.classList.add('strong'); }
}