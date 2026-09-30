# Gating artifacts

This directory is a repository-local output surface for generated Gating artifacts only.

Reports, evidence, cache data, checksums, and other generated verification artifacts may live here.
Executable rules, policy, schemas, and tooling come from the `gating/gate` Composer package.
Repository-specific executable configuration belongs in canonical Symfony/application configuration surfaces.
Do not copy the Gating engine into this directory.
