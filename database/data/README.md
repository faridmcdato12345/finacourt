# Philippine location reference data

`psgc-2026-07-13.json` is a compact application snapshot containing only the
Philippine regions, provinces, special geographic areas, cities, and
municipalities needed by the venue form. Barangays are intentionally excluded.

The records were normalized from the MIT-licensed `barangay` data package
snapshot dated 2026-07-13. That package publishes PSGC identifiers and
hierarchies derived from Philippine Statistics Authority releases. The JSON
metadata records both the snapshot URL and the authoritative PSA classification
page so provenance is retained with the data.

The form reads this catalog from MySQL rather than calling a remote service on
every page load. This keeps the application available during PSA/API outages and
prevents API credentials from being shipped to browsers. Import it idempotently
with:

```bash
docker compose exec app php artisan db:seed --class=Database\\Seeders\\PsgcLocationSeeder
```

Before replacing the snapshot, compare it with the current PSA PSGC publication,
review hierarchy changes, update the metadata/version, run the PSGC feature tests,
and verify the expected official totals. Do not silently overwrite owner-entered
venue coordinates or addresses during a catalog refresh.
