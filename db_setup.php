<?php
// Datenbank-Schema erstellen
// EINMAL aufrufen, dann diese Datei vom Server loeschen!

error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once __DIR__ . '/db.php';
    $pdo = getDB();
    echo "<p>DB-Verbindung OK</p>";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS kunden (
            id INT AUTO_INCREMENT PRIMARY KEY,
            vorname VARCHAR(100) NOT NULL,
            nachname VARCHAR(100) NOT NULL,
            email VARCHAR(255) NULL,
            telefon VARCHAR(30) NOT NULL,
            strasse VARCHAR(255) NULL,
            plz VARCHAR(10) NULL,
            ort VARCHAR(100) NULL,
            notizen TEXT NULL,
            erstellt_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            aktualisiert_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_telefon (telefon)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>Tabelle kunden erstellt</p>";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS anfragen (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kunden_id INT NOT NULL,
            formular_typ VARCHAR(100) NOT NULL,
            behandlung VARCHAR(255) NULL,
            koerperstelle VARCHAR(100) NULL,
            groesse VARCHAR(100) NULL,
            motiv TEXT NULL,
            referenzbild_pfad VARCHAR(500) NULL,
            wochentage VARCHAR(255) NULL,
            bevorzugte_uhrzeit VARCHAR(100) NULL,
            anmerkungen TEXT NULL,
            status ENUM('neu','bestaetigt','abgeschlossen','storniert') NOT NULL DEFAULT 'neu',
            admin_notizen TEXT NULL,
            erstellt_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            aktualisiert_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (kunden_id) REFERENCES kunden(id) ON DELETE CASCADE,
            INDEX idx_status (status),
            INDEX idx_kunden_id (kunden_id),
            INDEX idx_erstellt (erstellt_am)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>Tabelle anfragen erstellt</p>";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS datenschutz_dokumente (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kunden_id INT NOT NULL,
            dateiname VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            hochgeladen_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (kunden_id) REFERENCES kunden(id) ON DELETE CASCADE,
            INDEX idx_kunden_id (kunden_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>Tabelle datenschutz_dokumente erstellt</p>";

    // Besucher-Tracking Tabelle
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS besuche (
            id INT AUTO_INCREMENT PRIMARY KEY,
            seite VARCHAR(255) NOT NULL,
            referrer VARCHAR(500) NULL,
            ip_hash VARCHAR(64) NOT NULL,
            besucher_id VARCHAR(64) NOT NULL,
            user_agent VARCHAR(500) NULL,
            zeitpunkt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_zeitpunkt (zeitpunkt),
            INDEX idx_seite (seite),
            INDEX idx_besucher_id (besucher_id),
            INDEX idx_ip_hash_zeit (ip_hash, zeitpunkt)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>Tabelle besuche erstellt</p>";

    // Papierkorb: Soft-Delete Spalte hinzufuegen
    $spalte_exists = $pdo->query("
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'anfragen' AND COLUMN_NAME = 'geloescht_am'
    ")->fetchColumn();

    if (!$spalte_exists) {
        $pdo->exec("ALTER TABLE anfragen ADD COLUMN geloescht_am DATETIME NULL");
        echo "<p>Spalte geloescht_am hinzugefuegt</p>";
    } else {
        echo "<p>Spalte geloescht_am existiert bereits</p>";
    }


    echo "<h2>Alles erfolgreich!</h2>";
    echo "<p style='color:red;font-weight:bold;'>WICHTIG: Diese Datei jetzt vom Server loeschen!</p>";

} catch (Exception $e) {
    echo "<h2>FEHLER:</h2>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
