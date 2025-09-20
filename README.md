# Lets_Go_Docs — assets, ops notes, and web glue for the Lets Go platform

This repo centralizes **product docs, images/assets, deployment/playbooks, and web integrations** used across the project. It’s a reference archive for recruiters and developers exploring how the system was operated and shipped.

> **Contents:** product documentation · images/icons · fonts · admin notes · SSL/TLS test material · server deployment snippets · WordPress helpers (Django prototype kept for history)

---

## Highlights (skim me)

- **Images & icons:** complete activity/category icon set, app logos, store-listing art, and Android UI glyphs.
- **Operations:** server setup snippets (iptables, limits), MongoDB replica set notes, and release checklists.
- **Web tier glue:** WordPress **mu-plugins** for user flows (account recovery, email verification), plus an **obsolete Django** prototype.
- **Developer aids:** emulator setup, SSL test certs (non-production), and Qt Desktop Interface helper scripts.

---

## Repo map (what’s inside)

| Folder | What it contains | Notes |
|---|---|---|
| `Documentation/` | General notes and flow charts | e.g., `Android_Location_Request_Flow_Chart.png`, `General_Note.txt` |
| `Images/Activity_Icons/` | **Activity & category icons** organized by domain | Matches categories/activities used by matching; subfolders like **Pick-Up Sports**, **Food**, **Video Games**, etc. |
| `Images/Android_Basic_Icons/` | Common UI glyphs (PNG/SVG/zip) | Includes Material-style icons and a few vendor marks (see **Licensing** below). |
| `Images/Home_Screen_Images/` | App home screen backgrounds in multiple densities | For store shots and in-app backgrounds |
| `Images/Logo_Images/` | App logos (standard, inverted, white) + app icon | Source art for branding |
| `Images/Main_Store_Listing/` | Thumbs up/down, arrows, match logo, glove art | Used to compose store listing graphics |
| `Fonts/` | Allura, Courgette, Pacifico | Each includes its **OFL.txt** license |
| `Server_Deployment/` | Ops snippets: `Server_Setup.txt`, `iptables_*`, `limits_conf` | Practical notes for small-team Linux hosts |
| `Ssl_Keys_Testing/` | **Test** SSL material for gRPC & MongoDB | CA, replica members, and user certs for local/testing only |
| `Desktop_Interface/` | Qt admin helper scripts | `generate_proto_files.bat`, Windows build notes |
| `Web_Server/Wordpress/` | **mu-plugins** for user flows + styling | Account recovery, email verification, small utility glue |
| `Web_Server/Django_OBSOLETE/` | Early prototype web server | Kept for historical reference only |
| root files | `Checklist_Before_Release.txt`, `Manual_Testing.txt`, `MongoDbRsCommands.txt`, `Optimizations_And_Ideas_For_Later.txt`, `Android_Emulator_Testing_Setup.txt`, `LICENSE` | Shipping, QA, and ops checklists |

---

## Common tasks / quick paths

- **Find an activity icon:** `Images/Activity_Icons/<Category>/<Activity>/…`
- **Branding assets:** `Images/Logo_Images/` and `Images/Main_Store_Listing/`
- **Android UI glyphs:** `Images/Android_Basic_Icons/`
- **Local SSL testing:** `Ssl_Keys_Testing/` (gRPC, MongoDB CA + member/user certs)
- **Server bring-up notes:** `Server_Deployment/Server_Setup.txt` and `MongoDbRsCommands.txt`
- **Desktop Interface helpers:** `Desktop_Interface/qt6vars.cmd`, `generate_proto_files.bat`
- **Release hygiene:** `Checklist_Before_Release.txt`, `Manual_Testing.txt`

---

## Status & scope

This repo is a **portfolio/reference archive** for a completed system. Scripts/snippets reflect the setup used at the time and may be dated, but they show the operational approach (separate app and MongoDB hosts, TLS, checklists, and icon/asset organization).

---

## Licensing & attribution

- `Fonts/` include their own **OFL** licenses; keep those with any redistributed fonts.
- `Images/Android_Basic_Icons/` contains Material-style icons and vendor marks (e.g., Google/Maps pins, Facebook “f”). Verify license/attribution before reuse outside portfolio purposes.
- All other content in this repo is under the repo’s `LICENSE` unless otherwise noted in its folder.

---

## Related repositories

- **Server (C++)** — stateless hub, gRPC/Protobuf, MongoDB  
  👉 [`Lets_Go_Server`](https://github.com/lets-go-app-pub/Lets_Go_Server)

- **Android Client (Kotlin)** — auth, profiles, activities, chat *(SDK versions may be dated)*  
  👉 [`Lets_Go_Android_Client`](https://github.com/lets-go-app-pub/Lets_Go_Android_Client)

- **Desktop Admin (Qt)** — admin/ops console for moderation, events, stats, and controls  
  👉 [`Lets_Go_Interface`](https://github.com/lets-go-app-pub/Lets_Go_Interface)

- **Matching (Algo & Converter)** — Mongo aggregation (JS) + C++ converter to embed pipelines  
  👉 [`Lets_Go_Algorithm_And_Conversion`](https://github.com/lets-go-app-pub/Lets_Go_Algorithm_And_Conversion)

- **Protobuf Files** — protobuf files used to communicate between server and clients  
  👉 [`Lets_Go_Protobuf`](https://github.com/lets-go-app-pub/Lets_Go_Protobuf)

## License

See `LICENSE` in this repo; third-party assets retain their own licenses.
