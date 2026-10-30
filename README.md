# Maestro Travel

Statični prezentacijski primjer stranice turističke agencije Maestro Travel. HTML, CSS i JavaScript bez dodatnih paketa ili koraka izgradnje. Slike su lokalne, a relativne putanje podržavaju GitHub Pages adresu s nazivom repozitorija.

## Lokalni pregled

Na Macu otvorite `pokreni.command` ili pokrenite:

```sh
python3 start.py
```

Stranica se otvara na http://127.0.0.1:41740/. Zaustavite poslužitelj s Ctrl+C u njegovu prozoru. Port je odvojen od ranijeg primjera iz Codex radnog prostora.

## Objava na GitHub Pages

1. Na GitHubu napravite prazan repozitorij `maestro-travel`. Za jednostavnu objavu odaberite Public. Nemojte dodavati početni README ili .gitignore jer već postoje lokalno.
2. U Terminalu pokrenite naredbe niže. Zamijenite `TVOJE_GITHUB_IME` svojim stvarnim korisničkim imenom:

```sh
cd /Users/antonio/Code/maestro-travel
git add .
git commit -m "Initial Maestro Travel demo"
git remote add origin https://github.com/TVOJE_GITHUB_IME/maestro-travel.git
git push -u origin main
```

3. U repozitoriju otvorite **Settings → Pages**.
4. Pod **Build and deployment → Source** odaberite **Deploy from a branch**.
5. Odaberite granu **main**, folder **/(root)** i kliknite **Save**.
6. Kada objava završi, GitHub prikazuje poveznicu. Uobičajena adresa je `https://TVOJE_GITHUB_IME.github.io/maestro-travel/`.

Datoteka `index.html` već je u korijenu projekta, a `.nojekyll` isključuje Jekyll obradu. Nije potreban vlastiti GitHub Actions workflow ni Node.js.

Alternativno, u GitHub Desktopu dodajte ovu lokalnu mapu, napravite početni commit, odaberite **Publish repository**, a zatim uključite Pages prema koracima 3–6.

Službene upute: https://docs.github.com/en/pages/getting-started-with-github-pages/configuring-a-publishing-source-for-your-github-pages-site

## Daljnje izmjene

Nakon izmjena i lokalnog pregleda:

```sh
git add .
git commit -m "Update Maestro Travel demo"
git push
```

GitHub Pages ponovno objavljuje sadržaj odabrane grane.

## Što ovaj primjer radi

- Prikazuje tri destinacije, ručni hero, pretragu i filtre.
- Istanbul ima jedan izvorni arhivski termin i sedam jasno označenih oglednih termina; početna ogledna cijena iznosi 649 KM.
- Odabir termina mijenja datume i cijenu na istoj stranici.
- Ogledno uređivanje na `#admin` sprema promjene samo u lokalni preglednik. Te promjene ne ažuriraju GitHub, javni sadržaj ni druge uređaje. Za javne promjene u ovom primjeru mijenjaju se izvorne datoteke i šalju na GitHub.
- Nema produkcijske prijave, baze, slanja upita ni online rezervacija. Svi prikazani termini iz 2026. su prošli.

## Sadržaj i izvori

Izvorni primjeri ponuda:

- https://maestro.ba/istanbul-19-06-2026/
- https://maestro.ba/toskana-29-04-2026/
- https://maestro.ba/andaluzija-29-5-2026/

Naziv, logotip i fotografije Istanbula i Andaluzije preuzeti su s Maestro Travel stranice za prijedlog istoj agenciji. Fotografija Cinque Terre: https://unsplash.com/es/fotos/manarola-italia-rknrvCrfS1k. Programi su sažeti za prezentaciju. Prava na sadržaj treba potvrditi s agencijom prije javne objave; ovaj projekt ne daje dodatnu licencu na njihove materijale.
