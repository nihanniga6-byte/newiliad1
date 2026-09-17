# Graph Report - taghzie  (2026-09-17)

## Corpus Check
- cluster-only mode — file stats not available

## Summary
- 415 nodes · 618 edges · 55 communities (9 shown, 46 thin omitted)
- Extraction: 78% EXTRACTED · 22% INFERRED · 0% AMBIGUOUS · INFERRED: 138 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `8b35c909`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- Session
- api-sync.js
- Security
- User
- Database
- Cache
- Controller
- Session
- api/index.php
- Auth
- Model
- Logger
- ActivityLog
- app.js
- v2/composer.json
- FileHandler
- require
- ErrorHandler
- Router
- Validation
- .handle
- .handle

## God Nodes (most connected - your core abstractions)
1. `Session` - 38 edges
2. `Session` - 30 edges
3. `Logger` - 29 edges
4. `Auth` - 27 edges
5. `Security` - 26 edges
6. `Database` - 22 edges
7. `Controller` - 21 edges
8. `Model` - 20 edges
9. `current_user()` - 20 edges
10. `User` - 19 edges

## Surprising Connections (you probably didn't know these)
- `get_flash()` --calls--> `Session`  [INFERRED]
  v2/app/helpers/functions.php → v2/app/models/Session.php
- `is_admin()` --calls--> `Session`  [INFERRED]
  v2/app/helpers/functions.php → v2/app/models/Session.php
- `set_flash()` --calls--> `Session`  [INFERRED]
  v2/app/helpers/functions.php → v2/app/models/Session.php
- `csrf_field()` --calls--> `Security`  [INFERRED]
  v2/app/helpers/functions.php → v2/core/Security.php
- `csrf_token()` --calls--> `Security`  [INFERRED]
  v2/app/helpers/functions.php → v2/core/Security.php

## Import Cycles
- None detected.

## Communities (55 total, 46 thin omitted)

### Community 0 - "Session"
Cohesion: 0.08
Nodes (9): SettingsController, UserController, AuthController, DashboardController, current_user(), e(), redirect(), Session (+1 more)

### Community 1 - "api-sync.js"
Cohesion: 0.13
Nodes (30): buildPayload(), checkReady(), dequeueOutbox(), enqueueOutbox(), flushOutbox(), isPending(), loadFromApi(), done() (+22 more)

### Community 2 - "Security"
Cohesion: 0.06
Nodes (10): csrf_field(), csrf_token(), format_date(), get_flash(), is_admin(), sanitize(), set_flash(), time_ago() (+2 more)

### Community 4 - "Database"
Cohesion: 0.13
Nodes (4): PDO, self, Database, PDOStatement

### Community 6 - "Controller"
Cohesion: 0.11
Nodes (3): DownloadController, HomeController, Controller

### Community 8 - "api/index.php"
Cohesion: 0.28
Nodes (15): authLogin(), authLogout(), authMe(), authRegister(), dbToJs(), handleCrud(), handleDebug(), handleDeleteById() (+7 more)

### Community 13 - "app.js"
Cohesion: 0.20
Nodes (3): deleteUser(), showToast(), toggleUserStatus()

### Community 14 - "v2/composer.json"
Cohesion: 0.18
Nodes (10): description, name, require, ext-mbstring, ext-pdo, ext-pdo_mysql, php, scripts (+2 more)

### Community 16 - "require"
Cohesion: 0.22
Nodes (8): description, name, require, ext-json, ext-mbstring, ext-pdo, ext-pdo_mysql, php

## Knowledge Gaps
- **15 isolated node(s):** `description`, `name`, `ext-mbstring`, `ext-pdo`, `ext-pdo_mysql` (+10 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 216 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **46 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Model` connect `Model` to `Session`, `User`, `Database`, `Cache`, `ActivityLog`?**
  _High betweenness centrality (0.117) - this node is a cross-community bridge._
- **Why does `Logger` connect `Logger` to `Session`, `Security`, `Database`, `Controller`, `Auth`, `ActivityLog`, `FileHandler`, `ErrorHandler`, `Router`?**
  _High betweenness centrality (0.110) - this node is a cross-community bridge._
- **Why does `Session` connect `Session` to `Security`, `Model`, `ActivityLog`?**
  _High betweenness centrality (0.102) - this node is a cross-community bridge._
- **Are the 28 inferred relationships involving `Session` (e.g. with `.__construct()` and `.index()`) actually correct?**
  _`Session` has 28 INFERRED edges - model-reasoned connections that need verification._
- **Are the 13 inferred relationships involving `Session` (e.g. with `.check()` and `.login()`) actually correct?**
  _`Session` has 13 INFERRED edges - model-reasoned connections that need verification._
- **Are the 18 inferred relationships involving `Logger` (e.g. with `.file()` and `.log()`) actually correct?**
  _`Logger` has 18 INFERRED edges - model-reasoned connections that need verification._
- **Are the 8 inferred relationships involving `Auth` (e.g. with `.login()` and `.loginForm()`) actually correct?**
  _`Auth` has 8 INFERRED edges - model-reasoned connections that need verification._