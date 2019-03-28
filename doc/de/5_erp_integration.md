ERP-Integration
===

## Produkte
Ist das Paket `quiqqer/products` installiert, sind folgende Zusatz-Features für Mitgliedschaften verfügbar:

* Produkt-Feld "Mitgliedschaft" -> Über dieses Feld kann einem Produkt eine Mitgliedschaft zugewiesen werden, wird
dieses Produkt bestellt, wird der Bestell-Benutzer automatisch dieser Mitgliedschaft hinzugefügt.
* "Produkte"-Kategorie im Mitgliedschafts-Panel -> Hier sind alle Produkte gelistet, die diese Mitgliedschaft zugeordnet
haben. Über **Produkt erstellen** kann per Klick ein Produkt erzeugt werden, welches diese Mitgliedschaft automatisch
zugeordnet hat. 

## Verträge
Ist das Paket `quiqqer/contracts` installiert, sind folgende Zusatz-Features für Mitgliedschaften verfügbar. Dies setz voraus,
dass in den Mitgliedschafts-Einstellungen  der Punkt **Verknüpfung mit Verträgen** aktiviert ist.

* Wird ein Vertrag aus einer Bestellung erstellt, aus der auch eine Mitgliedschaft erstellt wird, wird die Mitgliedschaft
mit dem Vertrag verknüpft.
* Je nach Einstellung im Vertrags-Modul, wird mit Kündigung einer Mitgliedschaft auch der verknüpfte Vertrag automatisch
gekündigt (ggf. mit Beachtung der Kündigungsfrist). Dasselbe gilt für die Rücknahme der Kündigung.