CREATE OR REPLACE VIEW gesamt_punkte_wertung AS (
        SELECT p.wertungskategorie,
            p.wertungstyp,
            pe.startnummer,
            t.first_name AS vorname,
            t.last_name AS name,
            SUM(p.gutschrift) AS gesamtpunbktzahl
        FROM punkte_wertung p
            INNER JOIN punkte_ergebnis pe ON p.renn_ort = pe.renn_ort
            AND p.wertungskategorie = pe.wertungktegorie
            AND p.rundenanzahl = pe.rundenanzahl
            AND p.`position` = pe.`position`
            INNER JOIN teilnehmer t ON pe.startnummer = t.startnummer
        GROUP BY pe.startnummer,
            p.wertungskategorie,
            p.wertungstyp
        ORDER BY p.wertungstyp,
            gesamtpunbktzahl DESC
    )
CREATE OR REPLACE VIEW zeit_boni_je_fahrer AS (
        SELECT p.startnummer,
            p.punkte_gesamt as zeitgutschrift_s
        FROM punkte_gesamt_ergebnis p
        WHERE wertungstyp = "Zeit"
    )
CREATE OR REPLACE VIEW zeit_boni_je_fahrer_je_rennen AS (
        SELECT *
        FROM (
                SELECT p.wertungskategorie,
                    p.wertungstyp,
                    p.renn_ort,
                    pe.startnummer,
                    SUM(p.gutschrift) AS zeitgutschrift_s
                FROM punkte_wertung p
                    INNER JOIN punkte_ergebnis pe ON p.renn_ort = pe.renn_ort
                    AND p.wertungskategorie = pe.wertungktegorie
                    AND p.rundenanzahl = pe.rundenanzahl
                    AND p.`position` = pe.`position`
                    INNER JOIN teilnehmer t ON pe.startnummer = t.startnummer
                GROUP BY pe.startnummer,
                    p.wertungskategorie,
                    p.wertungstyp,
                    pe.renn_ort
            ) AS tmp
        WHERE tmp.wertungstyp = "Zeit"
    )
CREATE OR REPLACE VIEW bestzeiten_und_rundenanzahl AS (
        SELECT tmp.renn_ort,
            tmp.wertungskategorie,
            MIN(tmp.time_in_s) AS zeit_sieger,
            MAX(tmp.anzahl_runden) AS runden_sieger
        FROM (
                SELECT *
                FROM ergebnis e
                ORDER BY e.time_in_s
            ) AS tmp
        GROUP BY renn_ort,
            wertungskategorie
    )
CREATE OR REPLACE VIEW ergebnis_mit_runden AS (
        SELECT e.*,
            CASE
                WHEN e.anzahl_runden = runden_sieger THEN e.time_in_s
                WHEN e.anzahl_runden < runden_sieger THEN (
                    (runden_sieger - e.anzahl_runden) * (zeit_sieger / runden_sieger)
                ) + e.time_in_s
                ELSE e.time_in_s
            END AS time_in_s_mit_runden
        FROM ergebnis e
            INNER JOIN bestzeiten_und_rundenanzahl b ON e.renn_ort = b.renn_ort
            AND e.wertungskategorie = b.wertungskategorie
    )
CREATE OR REPLACE VIEW ergebnis_mit_runden_boni AS (
        SELECT er.*,
            CASE
                WHEN zb.zeitgutschrift_s IS NOT NULL THEN er.time_in_s_mit_runden - zb.zeitgutschrift_s
                ELSE er.time_in_s_mit_runden
            END AS time_in_s_mit_runden_boni,
            CASE
                WHEN zb.zeitgutschrift_s IS NOT NULL THEN er.time_in_s - zb.zeitgutschrift_s
                ELSE er.time_in_s
            END AS time_in_s_mit_boni
        FROM ergebnis_mit_runden er
            LEFT JOIN zeit_boni_je_fahrer_je_rennen zb ON er.startnummer = zb.startnummer
            AND er.renn_ort = zb.renn_ort
    )
CREATE OR REPLACE VIEW gesamt_zeit_wertung_ohne_boni AS (
        SELECT e.wertungskategorie,
            e.startnummer,
            e.name,
            e.vorname,
            SUM(e.time_in_s) AS total_time,
            SUM(e.time_in_s_mit_runden) AS total_time_runden
        FROM ergebnis_mit_runden e
        GROUP BY e.startnummer
        ORDER BY e.wertungskategorie,
            total_time
    )
CREATE OR REPLACE VIEW gesamt_zeit_wertung AS (
        SELECT e.wertungskategorie,
            e.startnummer,
            e.name,
            e.vorname,
            e.total_time,
            CASE
                WHEN zb.zeitgutschrift_s IS NOT NULL THEN e.total_time - zb.zeitgutschrift_s
                ELSE e.total_time
            END AS total_time_boni,
            CASE
                WHEN zb.zeitgutschrift_s IS NOT NULL THEN e.total_time_runden - zb.zeitgutschrift_s
                ELSE e.total_time_runden
            END AS total_time_runden_boni
        FROM gesamt_zeit_wertung_ohne_boni e
            LEFT JOIN zeit_boni_je_fahrer zb ON e.startnummer = zb.startnummer
        ORDER BY e.wertungskategorie,
            total_time
    )
CREATE OR REPLACE VIEW gesamt_zeit_wertung_mit_team AS (
        SELECT g.*,
            t.verein_team
        FROM gesamt_zeit_wertung g
            INNER JOIN teilnehmer t ON g.startnummer = t.startnummer
    )
SELECT *
FROM ergebnis_mit_runden er
    LEFT JOIN zeit_boni_je_fahrer_je_rennen zb ON er.startnummer = zb.startnummer er.renn_ort = zb.renn_ort