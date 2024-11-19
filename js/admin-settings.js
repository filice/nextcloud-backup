document.addEventListener('DOMContentLoaded', function () {
    const backupNowButton = document.getElementById('backup-now');
    const saveIntervalButton = document.getElementById('save-interval');
    const intervalInput = document.getElementById('backup-interval');

    // Backup Now
    if (backupNowButton) {
        backupNowButton.addEventListener('click', function () {
            backupNowButton.disabled = true;
            backupNowButton.textContent = 'Eseguendo Backup...';

            fetch(OC.generateUrl('/apps/backupplugin/backup/now'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'RequestToken': OC.requestToken
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    OC.Notification.showTemporary('Backup completato con successo!');
                } else {
                    OC.Notification.showTemporary('Errore durante il backup.');
                }
            })
            .catch(error => {
                console.error('Errore durante il backup:', error);
                OC.Notification.showTemporary('Si è verificato un errore inatteso.');
            })
            .finally(() => {
                backupNowButton.disabled = false;
                backupNowButton.textContent = 'Esegui Backup Ora';
            });
        });
    }

    // Salva intervallo backup
    if (saveIntervalButton) {
        saveIntervalButton.addEventListener('click', function () {
            saveIntervalButton.disabled = true;

            fetch(OC.generateUrl('/apps/backupplugin/settings/interval'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'RequestToken': OC.requestToken
                },
                body: JSON.stringify({
                    interval: intervalInput.value
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    OC.Notification.showTemporary('Intervallo di backup salvato!');
                } else {
                    OC.Notification.showTemporary('Errore durante il salvataggio dell\'intervallo.');
                }
            })
            .catch(error => {
                console.error('Errore durante il salvataggio dell\'intervallo:', error);
            })
            .finally(() => {
                saveIntervalButton.disabled = false;
            });
        });
    }
});
