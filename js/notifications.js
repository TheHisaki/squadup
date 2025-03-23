// Fonction pour initialiser le système de notifications
function initNotifications() {
    // Charger les notifications immédiatement
    updateNotifications();
    
    // Mettre à jour les notifications toutes les 30 secondes
    setInterval(updateNotifications, 30000);
}

// Fonction pour basculer l'affichage de la boîte de notifications
function toggleNotifications() {
    const notificationBox = document.getElementById('notification-box');
    notificationBox.classList.toggle('show');
}

// Fonction pour mettre à jour les notifications
function updateNotifications() {
    fetch('get_notifications.php')
    .then(response => response.json())
    .then(data => {
        const notificationList = document.getElementById('notification-list');
        const notificationCount = document.getElementById('notification-count');
        let unreadCount = 0;

        notificationList.innerHTML = '';
        
        data.forEach(notification => {
            if (!notification.is_read) unreadCount++;
            
            const notificationItem = document.createElement('div');
            notificationItem.className = `notification-item ${notification.is_read ? '' : 'unread'}`;
            
            // Créer le contenu de la notification
            const content = document.createElement('div');
            content.className = 'notification-content';
            content.innerHTML = `
                <strong>${notification.title}</strong>
                <p>${notification.message}</p>
                <small>${notification.created_at}</small>
            `;
            
            // Ajouter le gestionnaire de clic pour les demandes d'ami
            if (notification.type === 'friend_request' && notification.data && notification.data.user_id) {
                notificationItem.style.cursor = 'pointer';
                
                // Utiliser addEventListener au lieu de onclick
                notificationItem.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const userId = notification.data.user_id;
                    // Redirection avec un petit délai pour éviter les conflits
                    setTimeout(() => {
                        window.location.href = `view_profile.php?id=${userId}`;
                    }, 100);
                }, true);
            }
            
            notificationItem.appendChild(content);
            notificationList.appendChild(notificationItem);
        });

        if (unreadCount > 0) {
            notificationCount.style.display = 'flex';
            notificationCount.textContent = unreadCount;
        } else {
            notificationCount.style.display = 'none';
        }
    })
    .catch(error => {
        console.error('Erreur lors de la récupération des notifications:', error);
    });
}

// Fonction pour marquer toutes les notifications comme lues
function markAllAsRead() {
    fetch('mark_notifications_read.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateNotifications();
        }
    })
    .catch(error => {
        console.error('Erreur lors du marquage des notifications comme lues:', error);
    });
} 