(function() {
    'use strict';
    
    console.log(' [SYNC] Initialisation du système centralisé de notifications');
    
    const NotificationSync = {
        _count: 0,
        _initialized: false,
        _token: null,
        _updateInterval: null,
        
        // Initialiser le système
        async init() {
            if (this._initialized) {
                console.log(' [SYNC] Déjà initialisé');
                return;
            }
            
            this._token = localStorage.getItem('token') || localStorage.getItem('jwt_token');
            
            if (!this._token) {
                console.warn(' [SYNC] Pas de token - Mode déconnecté');
                this._initialized = true;
                this.setCount(0, 'no-token');
                return;
            }
            
            console.log(' [SYNC] Token trouvé, longueur:', this._token.length);
            
            // Charger le compteur initial 
            await this.refresh();
            
            // Mise à jour automatique toutes les 30 secondes
            if (this._updateInterval) {
                clearInterval(this._updateInterval);
            }
            
            this._updateInterval = setInterval(() => {
                console.log(' [SYNC] Refresh automatique...');
                this.refresh();
            }, 30000);
            
            this._initialized = true;
            console.log(' [SYNC] Système initialisé avec succès');
        },
        
        // Rafraîchir depuis l'API
        async refresh() {
            if (!this._token) {
                console.warn(' [SYNC] Pas de token pour rafraîchir');
                return;
            }
            
            try {
                console.log(' [SYNC] Appel API /notifications/count...');
                
                const response = await fetch('/notifications/count', {
                    method: 'GET',
                    headers: {
                        'Authorization': `Bearer ${this._token}`,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                });
                
                console.log(' [SYNC] Réponse API:', response.status);
                
                if (response.ok) {
                    const data = await response.json();
                    const count = data.count || 0;
                    console.log(' [SYNC] Compteur API reçu:', count);
                    this.setCount(count, 'api');
                } else if (response.status === 401) {
                    console.error(' [SYNC] Token invalide (401)');
                    this._token = null;
                    this.setCount(0, 'unauthorized');
                } else {
                    console.error(' [SYNC] Erreur API:', response.status);
                }
            } catch (error) {
                console.error(' [SYNC] Erreur réseau:', error);
            }
        },
        
        // Définir le compteur et notifier tous les composants
        setCount(count, source = 'unknown') {
            const oldCount = this._count;
            this._count = Math.max(0, parseInt(count) || 0);
            
            console.log(` [SYNC] Count: ${oldCount} → ${this._count} (source: ${source})`);
            
            // Dispatcher l'événement global IMMÉDIATEMENT
            const event = new CustomEvent('notificationCountUpdated', {
                detail: {
                    unreadCount: this._count,
                    oldCount: oldCount,
                    source: source,
                    timestamp: new Date().toISOString()
                }
            });
            
            window.dispatchEvent(event);
            
            console.log(' [SYNC] Événement dispatché:', {
                unreadCount: this._count,
                source: source
            });
        },
        
        // Obtenir le compteur actuel
        getCount() {
            return this._count;
        },
        
        // Vérifier si initialisé
        isInitialized() {
            return this._initialized;
        },
        
        // Forcer un refresh manuel
        forceRefresh() {
            console.log(' [SYNC] Refresh forcé par l\'utilisateur');
            return this.refresh();
        },
        
        // Nettoyer
        destroy() {
            if (this._updateInterval) {
                clearInterval(this._updateInterval);
                this._updateInterval = null;
            }
            this._initialized = false;
            console.log(' [SYNC] Système nettoyé');
        }
    };
    
    // Exposer globalement
    window.NotificationSync = NotificationSync;
    
    // Auto-initialisation avec délai
    function startInit() {
        console.log(' [SYNC] Démarrage de l\'initialisation...');
        setTimeout(() => {
            NotificationSync.init().then(() => {
                console.log(' [SYNC] Init terminée');
            }).catch(error => {
                console.error(' [SYNC] Erreur init:', error);
            });
        }, 100);
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            console.log(' [SYNC] DOM chargé');
            startInit();
        });
    } else {
        console.log(' [SYNC] DOM déjà chargé');
        startInit();
    }
    
    // Nettoyage avant fermeture
    window.addEventListener('beforeunload', () => {
        NotificationSync.destroy();
    });
    
    console.log(' [SYNC] notification-sync.js chargé');
})();