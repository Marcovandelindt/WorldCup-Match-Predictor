# GOALCAST — WK 2026 Match Predictor

Een op statistieken gebaseerde voorspellingstool voor het FIFA Wereldkampioenschap 2026. GOALCAST berekent per wedstrijd de verwachte doelpunten voor beide teams via een gewogen combinatie van vier databronnen, en vertaalt die naar scorelijnkansen via een Dixon-Coles gecorrigeerd Poisson-model.

## Tech stack

| Laag | Technologie |
|---|---|
| Backend | PHP 8.3 · Laravel 13 |
| Database | MySQL |
| Frontend | Vite 8 · SCSS (geen Tailwind) |
| Data | Kaggle CSV (historische interlands) · football-data.org API (speelschema) |

---

## Installatie

```bash
git clone <repo-url>
cd worldcup-match-predictor

composer install
npm install

cp .env.example .env
php artisan key:generate
```

Stel in `.env` je databaseverbinding en API-sleutel in:

```env
DB_DATABASE=worldcup
DB_USERNAME=root
DB_PASSWORD=

FOOTBALL_DATA_API_KEY=jouw_sleutel_hier
```

```bash
php artisan migrate
npm run build
```

---

## Data importeren

De datapipeline bestaat uit drie stappen die je in volgorde uitvoert:

### Stap 1 — Speelschema importeren

```bash
php artisan wk:import-schedule
```

Haalt het volledige WK 2026 speelschema op via de football-data.org API en slaat alle wedstrijden op in de `matches`-tabel, inclusief groepsindeling en speeldata.

### Stap 2 — Historische data importeren (Kaggle)

```bash
php artisan wk:import-historical-data --file="C:/pad/naar/results.csv"
```

Importeert de [Kaggle-dataset "International Football Results from 1872 to present"](https://www.kaggle.com/datasets/martj42/international-football-results-from-1872-to-2017) en vult drie zaken:

- **Vorm** (`team_recent_matches`): de laatste 30 interlands per team (alle competities)
- **WK-geschiedenis** (`team_recent_matches`): alle FIFA World Cup wedstrijden vanaf 1994
- **H2H** (`team_h2h_matches`): onderlinge duels tussen kwalificerende WK-teams
- **WK-gemiddelden** (`teams.avg_goals_scored_wc`, `avg_goals_conceded_wc`): gemiddelde doelpunten per wedstrijd op WK-toernooien

Opties:

```bash
--file=          Pad naar results.csv (verplicht)
--from=          Minimale datum voor vormmatch  [standaard: 2000-01-01]
--wc-from=       Minimale datum voor WK-match   [standaard: 1994-01-01]
```

### Stap 3 — Team- en H2H-data bijwerken via API (optioneel)

```bash
php artisan wk:import-team-data
```

Haalt aanvullende recente wedstrijden en H2H-data op via de football-data.org API. Kan worden overgeslagen als stap 2 voldoende data heeft opgeleverd.

### Uitslagen bijwerken

```bash
php artisan wk:update-results
```

Haalt gespeelde uitslagen op, slaat ze op en berekent de nauwkeurigheid van de voorspellingen (punten per wedstrijd).

---

## Voorspellingen genereren

Via de webinterface klik je per wedstrijd op **Genereer voorspelling**. Om alle wedstrijden in één keer te (her)berekenen:

```bash
# Herbereken wedstrijden zonder breakdown-data
php artisan wk:generate-predictions

# Bereken alle wedstrijden, ook die nog geen voorspelling hebben
php artisan wk:generate-predictions --all

# Forceer herberekening van alles
php artisan wk:generate-predictions --force
```

---

## Het voorspellingsmodel

GOALCAST gebruikt een **Dixon-Coles gecorrigeerd Poisson-model**. De kern van het model is het berekenen van twee verwachte doelpuntenaantallen (λ): één voor het thuisteam en één voor het uitteam. Die worden samengesteld uit vier componenten.

### Stap 1 — Vier componenten berekenen

Elk component levert een aanvalsterkte en verdedigingszwakte op, uitgedrukt als verhouding ten opzichte van het WK-gemiddelde (standaard **1,30 goals per team per wedstrijd**).

#### 1. Vorm (`FormAnalyzer`)

Op basis van de laatste **10 interlands** uit `team_recent_matches`:

```
aanvalsterkte  = gemiddeld_gescoord  / wc_gemiddelde
verdedigingszwakte = gemiddeld_gekregen / wc_gemiddelde

λ_vorm = aanvalsterkte × verdediging_tegenstander × wc_gemiddelde
```

#### 2. Head-to-Head (`HeadToHeadAnalyzer`)

Op basis van de laatste **10 onderlinge duels** uit `team_h2h_matches`. Dezelfde berekening als vorm, maar uitsluitend op resultaten tussen de twee specifieke teams.

```
λ_h2h = aanvalsterkte_h2h × verdedigingszwakte_h2h × wc_gemiddelde
```

Wanneer teams nog nooit eerder tegenover elkaar stonden, vervalt dit component en worden de gewichten herverdeeld.

#### 3. FIFA-ranking (`FifaRankingAnalyzer`)

Berekent een aanpassingsfactor op basis van het rangverschil:

```
verschil = uitranking − thuisranking
factor   = 1 + (verschil × 0,003)   →  begrensd op [0,70 – 1,30]

λ_fifa = λ_vorm × factor
```

Een thuisteam dat 100 plekken hoger staat dan de tegenstander krijgt een factor van ~1,30 (30% hoger verwacht); 100 plekken lager ~0,70.

#### 4. WK-geschiedenis (`WorldCupHistoryAnalyzer`)

Op basis van de gemiddelde doelpunten per wedstrijd op alle WK-edities (opgeslagen op het team-record):

```
aanvalsterkte_wk  = avg_goals_scored_wc  / wc_gemiddelde   (1,0 als geen data)
verdedigingszwakte_wk = avg_goals_conceded_wc / wc_gemiddelde (1,0 als geen data)

λ_wk = aanvalsterkte_wk × verdedigingszwakte_tegenstander_wk × wc_gemiddelde
```

---

### Stap 2 — Gewogen samenstelling

De vier λ-waarden worden samengevat tot één definitieve λ per team via een gewogen optelling:

**Met H2H-data beschikbaar:**

| Component | Gewicht |
|---|---|
| Vorm | 40% |
| Head-to-Head | 30% |
| FIFA-ranking | 20% |
| WK-geschiedenis | 10% |

```
λ_thuis = 0,40 × λ_vorm + 0,30 × λ_h2h + 0,20 × λ_fifa + 0,10 × λ_wk
```

**Zonder H2H-data:**

| Component | Gewicht |
|---|---|
| Vorm | 70% |
| FIFA-ranking | 20% |
| WK-geschiedenis | 10% |

```
λ_thuis = 0,70 × λ_vorm + 0,20 × λ_fifa + 0,10 × λ_wk
```

Hetzelfde geldt spiegelbeeldig voor `λ_uit`.

---

### Stap 3 — Poisson-kansen per scorelijn

Met de λ-waarden berekent het Poisson-model de kans op elk exact aantal doelpunten:

```
P(k doelpunten | λ) = (λ^k × e^(−λ)) / k!
```

De kans op een specifieke scorelijn is het product van beide kansen:

```
P(thuis=h, uit=u) = P(h | λ_thuis) × P(u | λ_uit)
```

Alle combinaties van 0–5 doelpunten per team worden berekend (36 scoreijnen).

---

### Stap 4 — Dixon-Coles correctie

Het standaard Poisson-model overschat gelijkspelen en 1-0-uitslagen enigszins. De Dixon-Coles correctie past lage-scorelijnkansen aan met factor ρ = **0,13**:

| Scorelijn | Correctie |
|---|---|
| 0 – 0 | × (1 − λ_t × λ_u × ρ) |
| 1 – 0 | × (1 + λ_u × ρ) |
| 0 – 1 | × (1 + λ_t × ρ) |
| 1 – 1 | × (1 − ρ) |
| overig | ongewijzigd |

De gecorrigeerde kansen worden gesorteerd en de **top 10 scoreijnen** worden opgeslagen.

---

### Win/gelijkspel/verlies-kansen

De win-, gelijkspel- en verlieskansen worden afgeleid door de scorelijnkansen te aggregeren:

```
P(thuiswinst)  = Σ P(h > u)
P(gelijkspel)  = Σ P(h = u)
P(uitwinst)    = Σ P(h < u)
```

---

## Projectstructuur (kernbestanden)

```
app/
├── Console/Commands/
│   ├── ImportHistoricalData.php   # Kaggle CSV importeur
│   ├── ImportSchedule.php         # WK 2026 speelschema via API
│   ├── ImportTeamData.php         # Team- en H2H-data via API
│   ├── UpdateResults.php          # Uitslagen ophalen + scoren
│   └── GeneratePredictions.php    # Batch herberekening voorspellingen
├── Http/Controllers/
│   ├── DashboardController.php
│   ├── PredictionController.php
│   ├── TeamController.php
│   └── ResultController.php
├── Models/
│   ├── FootballMatch.php          # matches-tabel (renamed vanwege PHP keyword)
│   ├── Team.php
│   ├── Prediction.php
│   ├── TeamRecentMatch.php
│   └── TeamH2hMatch.php
└── Services/
    ├── Predictor.php              # Orkestrator: bouwt λ en slaat prediction op
    ├── Analyzers/
    │   ├── FormAnalyzer.php
    │   ├── HeadToHeadAnalyzer.php
    │   ├── FifaRankingAnalyzer.php
    │   └── WorldCupHistoryAnalyzer.php
    └── Models/
        ├── PoissonModel.php
        └── DixonColesModel.php
```

---

## Puntensysteem (voorspellingsnauwkeurigheid)

| Resultaat | Punten |
|---|---|
| Exacte scorelijn correct | 3 |
| Winnaar correct (verkeerde score) | 1 |
| Winnaar incorrect | 0 |

---

## Licentie

MIT
