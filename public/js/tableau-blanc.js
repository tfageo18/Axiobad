/**
 * Tableau blanc "terrain de badminton" : éditeur de schémas tactiques en canvas pur JS, sans
 * dépendance externe. Utilisé en mode édition (src/Controller/SeanceController) et en mode
 * lecture seule (aperçu sur la fiche de séance / de créneau).
 *
 * Format de données stocké (JSON) :
 *   { "terrains": 1|2, "formes": [ {type, couleur, ...}, ... ] }
 * Toutes les coordonnées de formes sont des fractions (0..1) de la taille du canvas, pour que
 * le même schéma se redessine correctement quel que soit la taille réelle d'affichage (aperçu
 * miniature vs éditeur plein cadre).
 */
(function (global) {
    'use strict';

    function donneesParDefaut() {
        return { terrains: 1, formes: [] };
    }

    function rectanglesTerrains(largeur, hauteur, nbTerrains) {
        var marge = hauteur * 0.08;
        var rects = [];
        if (nbTerrains >= 2) {
            var gap = largeur * 0.04;
            var largeurTerrain = (largeur - gap - 2 * marge) / 2;
            rects.push({ x: marge, y: marge, w: largeurTerrain, h: hauteur - 2 * marge });
            rects.push({ x: marge + largeurTerrain + gap, y: marge, w: largeurTerrain, h: hauteur - 2 * marge });
        } else {
            rects.push({ x: marge, y: marge, w: largeur - 2 * marge, h: hauteur - 2 * marge });
        }
        return rects;
    }

    function dessinerUnTerrain(ctx, r) {
        ctx.save();
        ctx.strokeStyle = '#7dd3a0';
        ctx.lineWidth = 2;
        ctx.strokeRect(r.x, r.y, r.w, r.h);
        // Ligne de filet (verticale, au centre)
        ctx.beginPath();
        ctx.moveTo(r.x + r.w / 2, r.y);
        ctx.lineTo(r.x + r.w / 2, r.y + r.h);
        ctx.setLineDash([6, 4]);
        ctx.stroke();
        ctx.setLineDash([]);
        // Lignes de service (à ~1/5 de chaque côté du filet)
        var service = r.w * 0.16;
        ctx.beginPath();
        ctx.moveTo(r.x + r.w / 2 - service, r.y);
        ctx.lineTo(r.x + r.w / 2 - service, r.y + r.h);
        ctx.moveTo(r.x + r.w / 2 + service, r.y);
        ctx.lineTo(r.x + r.w / 2 + service, r.y + r.h);
        ctx.strokeStyle = 'rgba(125, 211, 160, 0.5)';
        ctx.stroke();
        // Ligne centrale de service (perpendiculaire)
        ctx.beginPath();
        ctx.moveTo(r.x, r.y + r.h / 2);
        ctx.lineTo(r.x + r.w / 2 - service, r.y + r.h / 2);
        ctx.moveTo(r.x + r.w / 2 + service, r.y + r.h / 2);
        ctx.lineTo(r.x + r.w, r.y + r.h / 2);
        ctx.stroke();
        ctx.restore();
    }

    function dessinerTerrains(ctx, largeur, hauteur, nbTerrains) {
        ctx.save();
        ctx.fillStyle = '#0f1f17';
        ctx.fillRect(0, 0, largeur, hauteur);
        ctx.restore();
        rectanglesTerrains(largeur, hauteur, nbTerrains).forEach(function (r) {
            dessinerUnTerrain(ctx, r);
        });
    }

    function dessinerForme(ctx, forme, largeur, hauteur) {
        ctx.save();
        ctx.strokeStyle = forme.couleur || '#ffffff';
        ctx.fillStyle = forme.couleur || '#ffffff';
        ctx.lineWidth = 3;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';

        if ('trait' === forme.type && forme.points && forme.points.length > 1) {
            ctx.beginPath();
            forme.points.forEach(function (p, i) {
                var x = p[0] * largeur, y = p[1] * hauteur;
                if (0 === i) { ctx.moveTo(x, y); } else { ctx.lineTo(x, y); }
            });
            ctx.stroke();
        } else if ('fleche' === forme.type && forme.de && forme.vers) {
            var x1 = forme.de[0] * largeur, y1 = forme.de[1] * hauteur;
            var x2 = forme.vers[0] * largeur, y2 = forme.vers[1] * hauteur;
            ctx.beginPath();
            ctx.moveTo(x1, y1);
            ctx.lineTo(x2, y2);
            ctx.stroke();
            var angle = Math.atan2(y2 - y1, x2 - x1);
            var tete = 12;
            ctx.beginPath();
            ctx.moveTo(x2, y2);
            ctx.lineTo(x2 - tete * Math.cos(angle - Math.PI / 6), y2 - tete * Math.sin(angle - Math.PI / 6));
            ctx.lineTo(x2 - tete * Math.cos(angle + Math.PI / 6), y2 - tete * Math.sin(angle + Math.PI / 6));
            ctx.closePath();
            ctx.fill();
        } else if ('joueur' === forme.type) {
            var cx = forme.x * largeur, cy = forme.y * hauteur;
            ctx.beginPath();
            ctx.arc(cx, cy, 13, 0, 2 * Math.PI);
            ctx.fill();
            if (forme.label) {
                ctx.fillStyle = '#0f1f17';
                ctx.font = 'bold 13px sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(forme.label, cx, cy + 1);
            }
        } else if ('texte' === forme.type && forme.texte) {
            ctx.font = 'bold 15px sans-serif';
            ctx.textAlign = 'left';
            ctx.textBaseline = 'top';
            ctx.fillText(forme.texte, forme.x * largeur, forme.y * hauteur);
        }
        ctx.restore();
    }

    /**
     * Redessine tout le canvas (fond terrain + formes) à partir des données.
     */
    function redessiner(canvas, donnees) {
        var ctx = canvas.getContext('2d');
        var largeur = canvas.width, hauteur = canvas.height;
        dessinerTerrains(ctx, largeur, hauteur, donnees.terrains || 1);
        (donnees.formes || []).forEach(function (forme) {
            dessinerForme(ctx, forme, largeur, hauteur);
        });
    }

    /**
     * Rendu en lecture seule dans un <canvas data-schema="...JSON..."> : lit les données depuis
     * l'attribut et dessine, sans aucune interactivité.
     */
    function initLectureSeule(canvas) {
        var donnees;
        try {
            donnees = JSON.parse(canvas.getAttribute('data-schema') || '{}');
        } catch (e) {
            donnees = donneesParDefaut();
        }
        if (!donnees.formes) { donnees = donneesParDefaut(); }
        redessiner(canvas, donnees);
    }

    /**
     * Initialise l'éditeur interactif. `options.champCache` est l'input hidden du formulaire où
     * le JSON est écrit à chaque modification (pour être soumis avec le reste du formulaire).
     */
    function initEditeur(canvas, options) {
        var donnees;
        try {
            donnees = JSON.parse(canvas.getAttribute('data-schema') || '{}');
        } catch (e) {
            donnees = donneesParDefaut();
        }
        if (!donnees.formes) { donnees = donneesParDefaut(); }

        var champCache = options.champCache;
        var selecteurOutil = options.selecteurOutil;
        var selecteurCouleur = options.selecteurCouleur;
        var selecteurTerrains = options.selecteurTerrains;
        var boutonAnnuler = options.boutonAnnuler;
        var boutonEffacer = options.boutonEffacer;

        var enCours = null; // forme en cours de dessin (trait/flèche)
        var enTrain = false;
        var prochainLabel = 65; // 'A'

        function sauvegarder() {
            champCache.value = JSON.stringify(donnees);
            redessiner(canvas, donnees);
        }

        function positionRelative(evt) {
            var rect = canvas.getBoundingClientRect();
            var x = (evt.clientX - rect.left) / rect.width;
            var y = (evt.clientY - rect.top) / rect.height;
            return [Math.max(0, Math.min(1, x)), Math.max(0, Math.min(1, y))];
        }

        function couleurCourante() {
            return selecteurCouleur ? selecteurCouleur.value : '#22c55e';
        }

        function outilCourant() {
            return selecteurOutil ? selecteurOutil.value : 'trait';
        }

        canvas.addEventListener('pointerdown', function (evt) {
            var p = positionRelative(evt);
            var outil = outilCourant();
            enTrain = true;

            if ('trait' === outil) {
                enCours = { type: 'trait', couleur: couleurCourante(), points: [p] };
            } else if ('fleche' === outil) {
                enCours = { type: 'fleche', couleur: couleurCourante(), de: p, vers: p };
            } else if ('joueur' === outil) {
                var label = window.prompt('Repère du joueur (1-2 lettres, optionnel) :', String.fromCharCode(prochainLabel));
                donnees.formes.push({ type: 'joueur', couleur: couleurCourante(), x: p[0], y: p[1], label: (label || '').slice(0, 2).toUpperCase() });
                prochainLabel = 65 + (donnees.formes.filter(function (f) { return 'joueur' === f.type; }).length % 26);
                enTrain = false;
                sauvegarder();
            } else if ('texte' === outil) {
                var texte = window.prompt('Texte à placer sur le schéma :', '');
                if (texte) {
                    donnees.formes.push({ type: 'texte', couleur: couleurCourante(), x: p[0], y: p[1], texte: texte });
                    sauvegarder();
                }
                enTrain = false;
            }
        });

        canvas.addEventListener('pointermove', function (evt) {
            if (!enTrain || !enCours) { return; }
            var p = positionRelative(evt);
            if ('trait' === enCours.type) {
                enCours.points.push(p);
            } else if ('fleche' === enCours.type) {
                enCours.vers = p;
            }
            redessiner(canvas, donnees);
            dessinerForme(canvas.getContext('2d'), enCours, canvas.width, canvas.height);
        });

        function terminerTrace() {
            if (enTrain && enCours) {
                if ('trait' === enCours.type && enCours.points.length > 1) {
                    donnees.formes.push(enCours);
                } else if ('fleche' === enCours.type) {
                    donnees.formes.push(enCours);
                }
                enCours = null;
                sauvegarder();
            }
            enTrain = false;
        }
        canvas.addEventListener('pointerup', terminerTrace);
        canvas.addEventListener('pointerleave', terminerTrace);

        if (selecteurTerrains) {
            selecteurTerrains.addEventListener('change', function () {
                donnees.terrains = parseInt(selecteurTerrains.value, 10) === 2 ? 2 : 1;
                sauvegarder();
            });
        }
        if (boutonAnnuler) {
            boutonAnnuler.addEventListener('click', function () {
                donnees.formes.pop();
                sauvegarder();
            });
        }
        if (boutonEffacer) {
            boutonEffacer.addEventListener('click', function () {
                if (window.confirm('Effacer tout le schéma ?')) {
                    donnees.formes = [];
                    sauvegarder();
                }
            });
        }

        sauvegarder();
    }

    global.TableauBlanc = {
        initLectureSeule: initLectureSeule,
        initEditeur: initEditeur,
        donneesParDefaut: donneesParDefaut,
    };
})(window);
