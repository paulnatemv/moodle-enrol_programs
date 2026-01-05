# Programs for Moodle - uTurn Education Custom UI

> **A beautiful, mobile-first fork of [Open LMS Programs](https://github.com/open-lms-open-source/moodle-enrol_programs) with modern UI enhancements**

[![Moodle](https://img.shields.io/badge/Moodle-4.5+-orange.svg)](https://moodle.org)
[![License](https://img.shields.io/badge/License-GPL%20v3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![Fork](https://img.shields.io/badge/Fork-Open%20LMS-green.svg)](https://github.com/open-lms-open-source/moodle-enrol_programs)

---

## Overview

This is a customized fork of the **Programs for Moodle** plugin by Open LMS, enhanced with a beautiful mobile-first user interface designed for **uTurn Education**. While maintaining full compatibility with the original plugin's functionality, this version provides a modern, visually appealing experience for students browsing and tracking their learning programs.

### Original Features (from Open LMS)
- Program content as hierarchy of courses and course sets
- Flexible sequencing rules
- Multiple allocation sources (Manual, Self-enrollment, Approval, Cohort, E-commerce)
- Advanced scheduling settings
- Course enrollment automation
- Program certificates

### Custom UI Enhancements (by uTurn Education)
- Modern card-based catalogue with grid/list toggle
- Beautiful program detail pages with tabbed interface
- Enhanced "My Programs" dashboard with progress tracking
- Real-time completion data visualization
- Mobile-first responsive design
- Alpine.js for smooth interactions

---

## Visual Overview

### 1. Program Catalogue (`/enrol/programs/catalogue/`)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  ┌─────────────────────────────────┐    ┌──────────────────────────────┐   │
│  │ 🔍 Search programs...           │    │  12 Programs   [▦] [≡]       │   │
│  └─────────────────────────────────┘    └──────────────────────────────┘   │
│                                                                             │
│  ┌─────────────────────┐  ┌─────────────────────┐  ┌─────────────────────┐ │
│  │ ┌─────────────────┐ │  │ ┌─────────────────┐ │  │ ┌─────────────────┐ │ │
│  │ │                 │ │  │ │                 │ │  │ │                 │ │ │
│  │ │   Program       │ │  │ │   Program       │ │  │ │   Program       │ │ │
│  │ │   Artwork       │ │  │ │   Artwork       │ │  │ │   Artwork       │ │ │
│  │ │   (16:9)        │ │  │ │   (16:9)        │ │  │ │   (16:9)        │ │ │
│  │ │                 │ │  │ │  ┌──────────┐   │ │  │ │                 │ │ │
│  │ └─────────────────┘ │  │ └──│ Enrolled │───┘ │  │ └─────────────────┘ │ │
│  │                     │  │    └──────────┘     │  │                     │ │
│  │ Certificate Program │  │ Leadership Academy  │  │ Sales Fundamentals  │ │
│  │                     │  │                     │  │                     │ │
│  │ Short description   │  │ Short description   │  │ Short description   │ │
│  │ of the program...   │  │ of the program...   │  │ of the program...   │ │
│  │                     │  │                     │  │                     │ │
│  │ 📚 12 courses       │  │ 📚 8 courses        │  │ 📚 6 courses        │ │
│  │                     │  │ ████████░░ 75%      │  │ 🛡️ Prerequisites    │ │
│  │ ┌───────┐ ┌───────┐ │  │ ┌───────┐ ┌───────┐ │  │ ┌───────┐           │ │
│  │ │ Tag 1 │ │ Tag 2 │ │  │ │ Tag 1 │ │ Tag 2 │ │  │ │ Tag 1 │           │ │
│  │ └───────┘ └───────┘ │  │ └───────┘ └───────┘ │  │ └───────┘           │ │
│  └─────────────────────┘  └─────────────────────┘  └─────────────────────┘ │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

**Features:**
- Grid/List view toggle (persisted in localStorage)
- Program artwork displayed in 16:9 aspect ratio
- "Enrolled" badge for allocated programs
- Progress bar for enrolled programs
- Course count and prerequisites indicator
- Tag display
- Search with instant filtering

---

### 2. Program Detail Page (`/enrol/programs/catalogue/program.php`)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  ← Back to Catalogue                                                        │
│                                                                             │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │                                                                         ││
│  │                         PROGRAM ARTWORK                                 ││
│  │                          (Hero Image)                                   ││
│  │                                                                         ││
│  └─────────────────────────────────────────────────────────────────────────┘│
│                                                                             │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │  Certificate - Foundations                                              ││
│  │  ┌─────────┐ ┌─────────┐ ┌─────────┐                                    ││
│  │  │ Sales   │ │ Growth  │ │ 2024    │          ┌─────────────────────┐   ││
│  │  └─────────┘ └─────────┘ └─────────┘          │     Sign Up         │   ││
│  │                                               └─────────────────────┘   ││
│  │  📚 12 Courses    🛡️ Prerequisites                                      ││
│  └─────────────────────────────────────────────────────────────────────────┘│
│                                                                             │
│  ┌──────────────┬──────────────┬──────────────┐                            │
│  │   Courses    │   Overview   │   Details    │  ← Alpine.js Tabs          │
│  └──────────────┴──────────────┴──────────────┘                            │
│                                                                             │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │  📖 COURSES IN THIS PROGRAM                                             ││
│  │                                                                         ││
│  │  ┌─────┬─────────────────────────────────────────────────────┬────────┐ ││
│  │  │  1  │  Introduction to Sales                              │   ✓    │ ││
│  │  ├─────┼─────────────────────────────────────────────────────┼────────┤ ││
│  │  │  2  │  Customer Psychology                                │  75%   │ ││
│  │  ├─────┼─────────────────────────────────────────────────────┼────────┤ ││
│  │  │  3  │  Negotiation Techniques                             │   ○    │ ││
│  │  ├─────┼─────────────────────────────────────────────────────┼────────┤ ││
│  │  │  4  │  Closing Strategies                                 │   ○    │ ││
│  │  └─────┴─────────────────────────────────────────────────────┴────────┘ ││
│  │                                                                         ││
│  │  Legend:  ✓ Completed   75% In Progress   ○ Not Started                 ││
│  └─────────────────────────────────────────────────────────────────────────┘│
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

**Features:**
- Hero image with program artwork
- Tag pills for categorization
- Action buttons (Sign Up, Request Access)
- Tabbed interface: Courses | Overview | Details
- Course list with real completion status
- Visual progress indicators (checkmark, percentage, circle)

---

### 3. My Programs Page (`/enrol/programs/my/`)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                                                                             │
│  My Programs                                          🔍 Browse Catalogue   │
│  Track your progress and continue learning                                  │
│                                                                             │
│  ┌───────────────────┐  ┌───────────────────┐  ┌───────────────────┐       │
│  │  📚               │  │  ✓                │  │  ◐                │       │
│  │  3                │  │  1                │  │  2                │       │
│  │  Total Programs   │  │  Completed        │  │  In Progress      │       │
│  └───────────────────┘  └───────────────────┘  └───────────────────┘       │
│                                                                             │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │                                                                         ││
│  │  ┌───────────────────────────┐  ┌───────────────────────────┐          ││
│  │  │ ┌───────────────────────┐ │  │ ┌───────────────────────┐ │          ││
│  │  │ │                       │ │  │ │                       │ │          ││
│  │  │ │    Program Artwork    │ │  │ │    Program Artwork    │ │          ││
│  │  │ │       (16:9)          │ │  │ │       (16:9)          │ │          ││
│  │  │ │  ┌──────────────────┐ │ │  │ │  ┌──────────────────┐ │ │          ││
│  │  │ └──│ ✓ Completed      │─┘ │  │ └──│ 75% Complete     │─┘ │          ││
│  │  │    └──────────────────┘   │  │    └──────────────────┘   │          ││
│  │  │                           │  │                           │          ││
│  │  │  Certificate Program      │  │  Leadership Academy       │          ││
│  │  │  ┌─────┐ ┌─────┐          │  │  ┌─────┐ ┌─────┐          │          ││
│  │  │  │ Tag │ │ Tag │          │  │  │ Tag │ │ Tag │          │          ││
│  │  │  └─────┘ └─────┘          │  │  └─────┘ └─────┘          │          ││
│  │  │                           │  │                           │          ││
│  │  │  12/12 courses    100%    │  │  6/8 courses       75%    │          ││
│  │  │  ████████████████████     │  │  ███████████████░░░░░     │          ││
│  │  │                           │  │                           │          ││
│  │  │  📅 Started: Jan 1, 2024  │  │  ⏰ Due: Mar 15, 2024     │          ││
│  │  │                           │  │  📅 Started: Jan 15, 2024 │          ││
│  │  │  ┌─────────────────────┐  │  │  ┌─────────────────────┐  │          ││
│  │  │  │   Review Program    │  │  │  │  Continue Learning  │  │          ││
│  │  │  └─────────────────────┘  │  │  └─────────────────────┘  │          ││
│  │  └───────────────────────────┘  └───────────────────────────┘          ││
│  │                                                                         ││
│  └─────────────────────────────────────────────────────────────────────────┘│
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

**Features:**
- Stats cards: Total, Completed, In Progress
- Program cards with artwork (16:9 aspect ratio)
- Status badge: Completed (green), In Progress (blue), Not Started (gray)
- Progress bar with percentage
- Course completion counter (X/Y courses)
- Due date with overdue highlighting
- Dynamic CTA: "Review Program" (completed) / "Continue Learning" (in progress)

---

### 4. My Program Detail (`/enrol/programs/my/program.php`)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  ← Back to My Programs                                                      │
│                                                                             │
│  ┌────────────────────────────────┐  ┌──────────────────────────────────┐  │
│  │                                │  │                                  │  │
│  │      Program Artwork           │  │  Certificate - Foundations       │  │
│  │         (Square)               │  │                                  │  │
│  │                                │  │  ┌─────────────────────────────┐ │  │
│  │                                │  │  │      Overall Progress       │ │  │
│  │                                │  │  │                             │ │  │
│  │                                │  │  │         ╭───────╮           │ │  │
│  │                                │  │  │        ╱    75%  ╲          │ │  │
│  │                                │  │  │       │           │         │ │  │
│  │                                │  │  │        ╲         ╱          │ │  │
│  │                                │  │  │         ╰───────╯           │ │  │
│  │                                │  │  │                             │ │  │
│  │                                │  │  │   6 of 8 courses complete   │ │  │
│  │                                │  │  └─────────────────────────────┘ │  │
│  └────────────────────────────────┘  └──────────────────────────────────┘  │
│                                                                             │
│  ┌──────────────┬──────────────┬──────────────┐                            │
│  │   Courses    │   Overview   │   Details    │                            │
│  └──────────────┴──────────────┴──────────────┘                            │
│                                                                             │
│  [Same course list as catalogue program detail]                             │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Technology Stack

| Technology | Purpose |
|------------|---------|
| **Alpine.js 3.x** | Lightweight reactivity for tabs, view toggles |
| **CSS Grid & Flexbox** | Responsive layouts |
| **CSS Custom Properties** | Theming with scoped variables (`--pc-`, `--pd-`, `--mp-`) |
| **Mustache Templates** | Moodle-native templating |
| **SVG Icons** | Crisp icons at any size (Lucide icon set) |

### CSS Architecture

```
styles.css (~1750 lines)
├── CSS Variables (theming)
├── Program Catalogue Styles
│   ├── .programs-catalogue
│   ├── .program-card (grid view)
│   ├── .program-list-item (list view)
│   └── .pc-progress-bar (prefixed to avoid Bootstrap conflicts)
├── Program Detail Styles
│   ├── .program-detail-page
│   ├── .pd-tabs (Alpine.js tabs)
│   └── .pd-course-list
├── My Programs Styles
│   ├── .my-programs-page
│   ├── .my-program-card
│   ├── .mp-progress-bar
│   └── .stat-card
└── Responsive Breakpoints
    ├── @media (max-width: 768px)
    └── @media (max-width: 480px)
```

---

## Files Modified/Added

### New Templates
| File | Description |
|------|-------------|
| `templates/catalogue.mustache` | Program catalogue with grid/list toggle |
| `templates/program_detail.mustache` | Program detail page with tabs |
| `templates/my_programs.mustache` | My Programs listing with stats |
| `templates/my_program_detail.mustache` | Individual program progress view |

### Modified Files
| File | Changes |
|------|---------|
| `styles.css` | +1500 lines of custom CSS |
| `classes/output/catalogue/renderer.php` | New template rendering, real completion data |
| `classes/output/my/renderer.php` | New My Programs rendering methods |
| `classes/local/catalogue.php` | Additional data for templates |
| `lang/en/enrol_programs.php` | New language strings |
| `my/index.php` | Custom renderer integration |
| `my/program.php` | Custom renderer integration |

---

## Installation

1. Download the latest release or clone this repository
2. Place in `/enrol/programs/` directory of your Moodle installation
3. Navigate to Site Administration → Notifications
4. Complete the upgrade process
5. Purge all caches

### Required Companion Plugins
- [moodle-local_openlms](https://github.com/open-lms-open-source/moodle-local_openlms)
- [moodle-block_myprograms](https://github.com/open-lms-open-source/moodle-block_myprograms) (optional)

---

## Recommended Image Sizes

| Image Type | Dimensions | Aspect Ratio |
|------------|------------|--------------|
| Program Artwork | 1920 × 1080 px | 16:9 |
| Thumbnail | 640 × 360 px | 16:9 |

---

## Credits

- **Original Plugin:** [Open LMS](https://github.com/open-lms-open-source/moodle-enrol_programs)
- **Custom UI:** [uTurn Education](https://uturn.education)
- **Development:** Built with [Claude Code](https://claude.com/claude-code)

---

## License

This plugin is licensed under the [GNU GPL v3](https://www.gnu.org/licenses/gpl-3.0.html), same as the original Open LMS plugin.

---

## Screenshots

> Add screenshots here showing the actual UI

| Catalogue Grid View | Catalogue List View |
|---------------------|---------------------|
| ![Grid View](docs/screenshots/catalogue-grid.png) | ![List View](docs/screenshots/catalogue-list.png) |

| Program Detail | My Programs |
|----------------|-------------|
| ![Detail](docs/screenshots/program-detail.png) | ![My Programs](docs/screenshots/my-programs.png) |
