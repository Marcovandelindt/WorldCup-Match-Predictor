# GOALCAST — WK 2026 Match Predictor

Een op statistieken gebaseerde voorspellingstool voor het FIFA Wereldkampioenschap 2026, gebouwd met Laravel 13. Voorspellingen worden berekend via een Dixon-Coles gecorrigeerd Poisson-model op basis van vier databronnen.

---

## Het voorspellingsmodel

### Stap 1 — Vier componenten berekenen

Elk component levert een aanvalsterkte en verdedigingszwakte op als verhouding tot het WK-gemiddelde (**1,30 goals per team per wedstrijd**).

#### 1. Vorm
Op basis van de laatste **10 interlands**:

```
aanvalsterkte      = gemiddeld_gescoord  / wc_gemiddelde
verdedigingszwakte = gemiddeld_gekregen  / wc_gemiddelde

λ_vorm = aanvalsterkte_thuis × verdedigingszwakte_uit × wc_gemiddelde
```

#### 2. Head-to-Head
Dezelfde berekening, maar uitsluitend op de laatste **10 onderlinge duels**. Vervalt automatisch als teams nog nooit eerder tegen elkaar speelden — de gewichten worden dan herverdeeld.

#### 3. FIFA-ranking
Berekent een aanpassingsfactor op basis van het rangverschil:

```
factor = 1 + ((uitranking − thuisranking) × 0,003)   →  begrensd op [0,70 – 1,30]

λ_fifa = λ_vorm × factor
```

Een thuisteam 100 plekken hoger dan de tegenstander krijgt factor ≈ 1,30; 100 plekken lager ≈ 0,70.

#### 4. WK-geschiedenis
Op basis van de gemiddelde doelpunten per wedstrijd op alle WK-edities (opgeslagen op het teamrecord, berekend vanaf WK 1994):

```
λ_wk = (avg_goals_scored_wc / wc_gem.) × (avg_goals_conceded_wc_tegenstander / wc_gem.) × wc_gem.
```

Valt terug op 1,0 (neutraal) als een team geen WK-historie heeft.

---

### Stap 2 — Gewogen samenstelling

**Met H2H-data:**

| Component | Gewicht |
|---|---|
| Vorm | 40% |
| Head-to-Head | 30% |
| FIFA-ranking | 20% |
| WK-geschiedenis | 10% |

**Zonder H2H-data:**

| Component | Gewicht |
|---|---|
| Vorm | 70% |
| FIFA-ranking | 20% |
| WK-geschiedenis | 10% |

```
λ_thuis = Σ (gewicht_i × λ_i)
λ_uit   = Σ (gewicht_i × λ_i)
```

---

### Stap 3 — Poisson-kansen per scorelijn

```
P(k doelpunten | λ) = (λ^k × e^(−λ)) / k!

P(thuis=h, uit=u) = P(h | λ_thuis) × P(u | λ_uit)
```

Alle combinaties van 0–5 doelpunten per team worden doorgerekend (36 scoreijnen).

---

### Stap 4 — Dixon-Coles correctie

Corrigeert de systematische over/onderschatting van lage scoreijnen door Poisson (ρ = **0,13**):

| Scorelijn | Correctie |
|---|---|
| 0 – 0 | × (1 − λ_t × λ_u × ρ) |
| 1 – 0 | × (1 + λ_u × ρ) |
| 0 – 1 | × (1 + λ_t × ρ) |
| 1 – 1 | × (1 − ρ) |
| overig | ongewijzigd |

De gecorrigeerde kansen worden gesorteerd; de **top 10 scoreijnen** worden opgeslagen.

---

### Win/gelijkspel/verlies-kansen

```
P(thuiswinst) = Σ P(h > u)
P(gelijkspel) = Σ P(h = u)
P(uitwinst)   = Σ P(h < u)
```

---

### Puntensysteem

| Resultaat | Punten |
|---|---|
| Exacte scorelijn correct | 3 |
| Winnaar correct (verkeerde score) | 1 |
| Winnaar incorrect | 0 |
