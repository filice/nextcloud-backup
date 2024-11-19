<div class="section" id="backupplugin-settings">
    <h2>Impostazioni Backup Plugin</h2>
    <form id="backupplugin-settings-form">
        <label for="file-backup-folder">Cartella di Backup dei File:</label>
        <input type="text" id="file-backup-folder" name="file_backup_folder" value="<?php p($_['file_backup_folder']); ?>" />

        <label for="db-backup-folder">Cartella di Backup del Database:</label>
        <input type="text" id="db-backup-folder" name="db_backup_folder" value="<?php p($_['db_backup_folder']); ?>" />

        <button type="submit" id="save-settings">Salva Impostazioni</button>
    </form>

    <h3>Backup Manuale</h3>
    <button id="backup-now">Esegui Backup Ora</button>

    <h3>Backup Automatico</h3>
    <label for="backup-interval">Intervallo Backup (in ore):</label>
    <input type="number" id="backup-interval" name="backup_interval" value="<?php p($_['backup_interval']); ?>" />
    <button id="save-interval">Salva Intervallo</button>
</div>
