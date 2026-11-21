# Note Codacy

## Falsi positivi noti

- `Lizard_file-nloc-critical` su `composer.lock`: il file e' generato da
  Composer e contiene gli hash di blocco delle dipendenze. Non e' codice
  sorgente e non puo' essere diviso senza rompere la riproducibilita' degli
  install. Va escluso lato analisi, non riscritto.
- `Semgrep` su `Dockerfile` e `k8s/`: i avvisi su digest delle immagini,
  NetworkPolicy, Ingress NGINX e limiti CPU frazionari richiedono scelte di
  infrastruttura (quale registro, quale controller, quale quota). Non sono
  correzioni di codice applicabili senza decisione del manutentore.
- `Agentlinter_clarity_undefined-term`: acronimi di dominio nei documenti;
  vanno definiti in glossario, non soppressi.

Nessuna di queste voci e' stata chiusa come "accepted" o "won't fix": qui
sono solo documentate con la motivazione.
