document.addEventListener('DOMContentLoaded', function () {
    const backupNowButton = document.getElementById('backup-now');
    const saveIntervalButton = document.getElementById('save-interval');
    const intervalInput = document.getElementById('backup-interval');
    const settingsForm = document.getElementById('nextcloud_backup-settings-form');

    // Backup Now
    if (backupNowButton) {
        backupNowButton.addEventListener('click', function () {
            backupNowButton.disabled = true;
            backupNowButton.textContent = 'Backup in corso...';
    
            fetch(OC.generateUrl('/apps/nextcloud_backup/settings/backup/now'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'RequestToken': OC.requestToken
                }
            })
            .then(() => {
                // Inizia a monitorare lo stato del backup
                monitorBackupStatus();
            })
            .catch(error => {
                console.error('Errore durante l\'avvio del backup:', error);
                OC.Notification.showTemporary('Errore durante l\'avvio del backup.');
                backupNowButton.disabled = false;
                backupNowButton.textContent = 'Esegui Backup Ora';
            });
        });
    }
    
    function monitorBackupStatus() {
        const interval = setInterval(() => {
            fetch(OC.generateUrl('/apps/nextcloud_backup/settings/backup/status'), {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'RequestToken': OC.requestToken
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    clearInterval(interval);
                    OC.Notification.showTemporary('Backup completato con successo!');
                    backupNowButton.disabled = false;
                    backupNowButton.textContent = 'Esegui Backup Ora';
                } else if (data.status === 'error') {
                    clearInterval(interval);
                    OC.Notification.showTemporary('Errore durante il backup.');
                    backupNowButton.disabled = false;
                    backupNowButton.textContent = 'Esegui Backup Ora';
                }
            })
            .catch(error => {
                console.error('Errore durante il monitoraggio del backup:', error);
                clearInterval(interval);
                OC.Notification.showTemporary('Errore durante il monitoraggio del backup.');
                backupNowButton.disabled = false;
                backupNowButton.textContent = 'Esegui Backup Ora';
            });
        }, 5000); // Controlla lo stato ogni 5 secondi
    }
    

    // Salva intervallo backup
    if (saveIntervalButton) {
        saveIntervalButton.addEventListener('click', function () {
            saveIntervalButton.disabled = true;

            fetch(OC.generateUrl('/apps/nextcloud_backup/settings/interval'), {
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

    // Salva impostazioni principali
    if (settingsForm) {
        settingsForm.addEventListener('submit', function (event) {
            event.preventDefault(); // Previene il comportamento predefinito del form

            const fileBackupFolder = document.getElementById('file-backup-folder').value;
            const dbBackupFolder = document.getElementById('db-backup-folder').value;

            fetch(OC.generateUrl('/apps/nextcloud_backup/settings/admin/save'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'RequestToken': OC.requestToken
                },
                body: JSON.stringify({
                    fileBackupFolder,
                    dbBackupFolder
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    OC.Notification.showTemporary('Impostazioni salvate con successo!');
                } else {
                    OC.Notification.showTemporary('Errore durante il salvataggio delle impostazioni.');
                }
            })
            .catch(error => {
                console.error('Errore durante il salvataggio delle impostazioni:', error);
                OC.Notification.showTemporary('Si è verificato un errore inatteso.');
            });
        });
    }
});
