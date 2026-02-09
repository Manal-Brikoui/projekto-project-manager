
(function() {
    'use strict';
    
    console.log(' [BADGE-SYNC] Initialisation du système de synchronisation');
    
    //  CONFIGURATION 
    const CONFIG = {
        // Liste de TOUS les IDs de badges utilisés
        BADGE_IDS: [
            'notifBadge',
            'notifBadgeHeader', 
            'notifBadgeNavbar',
            'notifBadgeSidebar',
            'notifBadgeIcon'
        ],
        
        // Intervalle de mise à jour (30 secondes)
        UPDATE_INTERVAL: 30000,
        
        // Endpoint API
        API_ENDPOINT: '/notifications/count'
    };
    
    let currentCount = 0;
    let updateInterval = null;
    
    //  MISE À JOUR DE TOUS LES BADGES
    function updateAllBadges(count) {
        console.log(` [BADGE-SYNC] Mise à jour de tous les badges avec count: ${count}`);
        
        let updatedCount = 0;
        
        CONFIG.BADGE_IDS.forEach(badgeId => {
            const badge = document.getElementById(badgeId);
            
            if (badge) {
                if (count > 0) {
                    badge.textContent = count > 99 ? '99+' : count;
                    badge.classList.remove('hidden');
                    badge.style.display = 'flex';
                } else {
                    badge.classList.add('hidden');
                    badge.style.display = 'none';
                }
                
                updatedCount++;
                console.log(` Badge "${badgeId}" mis à jour`);
            }
        });
        
        console.log(` [BADGE-SYNC] ${updatedCount} badge(s) mis à jour sur ${CONFIG.BADGE_IDS.length} possibles`);
        
        // Sauvegarder le count actuel
        currentCount = count;
    }
    
    // RÉCUPÉRATION DU COUNT DEPUIS L'API 
    async function fetchNotificationCount() {
        try {
            const token = localStorage.getItem('token') || localStorage.getItem('jwt_token');
            
            if (!token) {
                console.log(' [BADGE-SYNC] Pas de token disponible');
                return;
            }
            
            console.log(' [BADGE-SYNC] Récupération du count depuis l\'API...');
            
            const response = await fetch(CONFIG.API_ENDPOINT, {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                }
            });
            
            if (!response.ok) {
                if (response.status === 401) {
                    console.log(' [BADGE-SYNC] Session expirée');
                } else {
                    console.error(' [BADGE-SYNC] Erreur HTTP:', response.status);
                }
                return;
            }
            
            const data = await response.json();
            const newCount = data.count || 0;
            
            console.log(` [BADGE-SYNC] Count récupéré: ${newCount}`);
            
            // Mettre à jour uniquement si le count a changé
            if (newCount !== currentCount) {
                console.log(` [BADGE-SYNC] Count modifié: ${currentCount} → ${newCount}`);
                updateAllBadges(newCount);
                
                // Dispatcher un événement global
                window.dispatchEvent(new CustomEvent('notificationCountUpdated', {
                    detail: { 
                        unreadCount: newCount,
                        previousCount: currentCount,
                        source: 'badge-sync'
                    }
                }));
            } else {
                console.log(' [BADGE-SYNC] Aucun changement détecté');
            }
            
        } catch (error) {
            console.error(' [BADGE-SYNC] Erreur lors de la récupération:', error);
        }
    }
    
    //  INITIALISATION 
    function initBadgeSync() {
        console.log(' [BADGE-SYNC] Démarrage du système');
        
        // Première récupération immédiate
        fetchNotificationCount();
        
        // Nettoyage de l'ancien intervalle si existant
        if (updateInterval) {
            clearInterval(updateInterval);
        }
        
        // Mise à jour périodique
        updateInterval = setInterval(fetchNotificationCount, CONFIG.UPDATE_INTERVAL);
        
        console.log(` [BADGE-SYNC] Mise à jour automatique configurée (${CONFIG.UPDATE_INTERVAL / 1000}s)`);
    }
    
    // ÉCOUTE DES ÉVÉNEMENTS EXTERNES
    window.addEventListener('notificationCountUpdated', (event) => {
        // Ne pas créer de boucle infinie
        if (event.detail && event.detail.source !== 'badge-sync') {
            const count = event.detail.unreadCount;
            console.log(` [BADGE-SYNC] Événement externe reçu: ${count} (source: ${event.detail.source})`);
            
            if (count !== currentCount) {
                updateAllBadges(count);
            }
        }
    });
    
  
    // Exposer des fonctions pour une utilisation externe
    window.NotificationBadgeSync = {
        // Récupérer le count actuel
        getCount: function() {
            return currentCount;
        },
        
        // Forcer une mise à jour
        forceUpdate: function() {
            console.log(' [BADGE-SYNC] Mise à jour forcée demandée');
            fetchNotificationCount();
        },
        
        // Mettre à jour manuellement le count 
        setCount: function(count) {
            console.log(` [BADGE-SYNC] Mise à jour manuelle: ${count}`);
            updateAllBadges(count);
        },
        
        // Vérifier si le système est initialisé
        isInitialized: function() {
            return updateInterval !== null;
        }
    };
    
    // NETTOYAGE 
    window.addEventListener('beforeunload', () => {
        console.log(' [BADGE-SYNC] Nettoyage avant fermeture');
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
        }
    });
    
    //  DÉMARRAGE AUTOMATIQUE 
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBadgeSync);
    } else {
       
        setTimeout(initBadgeSync, 100);
    }
    
    console.log(' [BADGE-SYNC] Système de synchronisation chargé');
})();