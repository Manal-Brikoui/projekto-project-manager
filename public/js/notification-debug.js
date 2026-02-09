
(function () {
    'use strict';

    console.log(' [DEBUG] Notification Debug chargé');

    function logState() {
        console.group(' ÉTAT NOTIFICATIONS');
        console.log('NotificationSync existe ?', !!window.NotificationSync);

        if (window.NotificationSync) {
            console.log('Unread count :', window.NotificationSync.getCount());
        } else {
            console.warn('NotificationSync NON disponible');
        }

        console.groupEnd();
    }

    // Vérifier après chargement DOM
    document.addEventListener('DOMContentLoaded', () => {
        console.log(' DOM chargé → vérification notifications');
        logState();
    });

    // Vérifier après réception d’un event
    window.addEventListener('notificationCountUpdated', (event) => {
        console.log(' Event notificationCountUpdated reçu');
        console.log('Détails :', event.detail);
        logState();
    });

    // Vérification tardive 
    setTimeout(() => {
        console.log(' Vérification tardive (1s)');
        logState();
    }, 1000);

})();
