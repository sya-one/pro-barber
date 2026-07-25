// Main website JavaScript
$(document).ready(function() {
    // Sticky header shrink on scroll
    $(window).scroll(function() {
        if ($(this).scrollTop() > 100) {
            $('.top-navbar').addClass('shrink');
        } else {
            $('.top-navbar').removeClass('shrink');
        }
    });
    
    // Smooth scroll for anchor links
    $('a[href^="#"]:not([href="#"])').on('click', function(e) {
        e.preventDefault();
        $('html, body').animate({
            scrollTop: $($(this).attr('href')).offset().top - 80
        }, 800);
    });
    
    // Lazy loading for images
    $('img[data-src]').each(function() {
        if ($(this).isInViewport()) {
            $(this).attr('src', $(this).data('src')).removeAttr('data-src');
        }
    });
    
    $(window).on('scroll resize', function() {
        $('img[data-src]').each(function() {
            if ($(this).isInViewport()) {
                $(this).attr('src', $(this).data('src')).removeAttr('data-src');
            }
        });
    });
    
    // Intersection Observer for scroll animations
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('aos-animate');
            }
        });
    }, { threshold: 0.1 });
    
    document.querySelectorAll('[data-aos]').forEach(el => {
        observer.observe(el);
    });
});

// Check if element is in viewport
$.fn.isInViewport = function() {
    var elementTop = $(this).offset().top;
    var elementBottom = elementTop + $(this).outerHeight();
    var viewportTop = $(window).scrollTop();
    var viewportBottom = viewportTop + $(window).height();
    return elementBottom > viewportTop && elementTop < viewportBottom;
};

// Update cart count
function updateCartCount() {
    $.get('cart_ajax.php', { action: 'count' }, function(data) {
        $('#cartBadge').text(data.count);
    }, 'json');
}

// Newsletter signup
function subscribeNewsletter(email) {
    $.post('ajax/newsletter.php', { email: email }, function(data) {
        if (data.success) {
            alert('Thank you for subscribing!');
        } else {
            alert(data.message);
        }
    }, 'json');
}