# Changelog

## 2.5.0

- Add registered immutable business outcome events with English and Persian discovery metadata.
- Publish minimal allowlisted identifiers after the owning transaction commits; preserve all legacy event signatures and behavior.
- Suppress outcomes for validation failures, rollback and unchanged business state. Listener failures cannot invalidate committed writes. Delivery is synchronous without an outbox.


## 2.4.4 - 2026-10-11

- Order flow instances by their actual `started_at` column when finding active instances.
