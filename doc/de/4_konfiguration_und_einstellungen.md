Konfiguration & Einstellungen
===

## Konfiguration
### Cron einrichten
Unter `Verwaltung -> Crons -> Cron-Manager -> Aufgabe hinzufügen` muss der Cron **Mitgliedschaften prüfen/verlängern**
eingerichtet werden.

Dieser sorgt dafür, dass Benutzer automatisiert aus Mitgliedschaften entfernt werden oder ihre Laufzeit verlängert wird.

Je nach Laufzeit-Einstellunge (tag- oder sekundengenau) ist es empfehlenswert, den Cron **minütlich** oder **täglich**
auszuführen.

## Einstellungen
Unter `Einstellungen -> Mitgliedschaften` können alle Einstellungen für das Mitgliedschafts-Modul festgelegt werden.
Jede Einstellung ist dort ausführlich beschrieben.

Mit den Standard-Einstellungen lässt sich das Mitgliedschafts-Modul bereits vollständig nutzen. Zu beachten ist,
dass für den Versand von **E-Mails** die QUIQQER E-Mail Einstellungen (`Einstellungen -> QUIQQER -> System -> E-Mail`)
korrekt gesetzt sind.

### Verlängerung von Mitgliedschaften für aktive Mitglieder
Wird die Laufzeit einer Mitgliedschaftszugehörigkeit für einen Benutzer verlängert, kann das Start- und End-Datum für
den Turnus entweder neu gesetzt werden (für den Zeitraum des neuen Turnus) oder nur das End-Datum auf das Ende
des neuen Turnus gesetzt werden.

Dies hat lediglich Auswirkungen auf die **Anzeige** für den Benutzer im Frontend und verändert nicht das Verhalten der
Zugehörigkeit.

### Genauigkeit von Laufzeiten
Bei **tag-genauer** Laufzeit wird das End-Datum auf das Ende des End-Tags aufgerundet.

Beispiel:

Laufzeit:           1 Woche                                        
Start-Zeitpunkt:    Montag, 04.03.2019, 15:00:00 Uhr
End-Zeitpunkt:      Montag, 11.03.2019, 23:59:59 Uhr

Bei **sekunden-genauer** Laufzeit sieht das Beispiel wie folgt aus:

Laufzeit:           1 Woche                                        
Start-Zeitpunkt:    Montag, 04.03.2019, 15:00:00 Uhr
End-Zeitpunkt:      Montag, 11.03.2019, 15:00:00 Uhr