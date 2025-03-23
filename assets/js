document.addEventListener('DOMContentLoaded', () => {
    // Gestion de l'état de connexion
    const authLinks = document.getElementById('authLinks');
    const userMenu = document.getElementById('userMenu');

    // Vérifier si l'utilisateur est connecté via une requête AJAX
    fetch('check_auth.php')
        .then(response => response.json())
        .then(data => {
            if (data.isLoggedIn) {
                authLinks.classList.add('hidden');
                userMenu.classList.remove('hidden');
            } else {
                authLinks.classList.remove('hidden');
                userMenu.classList.add('hidden');
            }
        });

    // Animation des cartes au scroll
    const cards = document.querySelectorAll('.feature-card');
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    cards.forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(50px)';
        card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        observer.observe(card);
    });

    // Animation du titre glitch
    const glitchText = document.querySelector('.glitch');
    if (glitchText) {
        setInterval(() => {
            glitchText.style.textShadow = `
                ${Math.random() * 10 - 5}px ${Math.random() * 10 - 5}px var(--primary-color),
                ${Math.random() * 10 - 5}px ${Math.random() * 10 - 5}px var(--secondary-color),
                ${Math.random() * 10 - 5}px ${Math.random() * 10 - 5}px var(--accent-color)
            `;
            setTimeout(() => {
                glitchText.style.textShadow = '';
            }, 50);
        }, 3000);
    }

    // Animation des boutons
    const buttons = document.querySelectorAll('.cta-primary, .cta-secondary');
    buttons.forEach(button => {
        button.addEventListener('mouseover', () => {
            button.style.transform = 'translateY(-3px)';
            button.style.boxShadow = '0 5px 15px rgba(138, 43, 226, 0.3)';
        });

        button.addEventListener('mouseout', () => {
            button.style.transform = 'translateY(0)';
            button.style.boxShadow = 'none';
        });
    });
});