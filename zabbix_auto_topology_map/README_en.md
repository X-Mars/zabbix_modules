# Auto Topology Map (Zabbix frontend module `topomap`)

Adds a new page under **Monitoring → Auto topology map**: create a map, list maps by name, click a name to preview its canvas.
In "Edit map" you can add and remove **map elements / shapes / links**; elements support **images** and **hosts**
(host elements carry problem tags, icons are chosen automatically, and there are four icon sets: default / problem / maintenance / disabled).
The page header also has **Auto discovery**: pick a few switches as starting points, read the SNMP LLDP / CDP neighbor tables, and generate a map automatically.

Data is read from and written directly to the native Zabbix `sysmaps` (`map.create` / `map.update` / `map.delete`),
so maps drawn in this module and those under "Monitoring → Topology map" are **the same data**: visible and editable from both sides.

The state of the list / canvas is **remembered** just like the native UI: navigate away from the canvas page and clicking the menu later returns to the same map;
navigate away from the list and clicking back returns to the list.

---

## UI and operations

The list page deliberately follows native Zabbix **Monitoring → Topology map**: page header title on the left + `Create map` at the top right,
below it a collapsible **filter** (name + Apply / Reset), a `Name / Width (px) / Height (px) / Actions` table,
and a fixed action bar at the bottom (`N selected` / Export / Delete). It uses the theme's own class names, so light and dark themes both follow the theme.

| Location | Element | Description |
| --- | --- | --- |
| Page header | **Auto discovery** | Select starting devices → read LLDP/CDP neighbors → generate a map automatically (see the next section) |
| Page header | **Create map** | Opens a form: **Name** + **Apply** + **Reset**; canvas size is automatic, no need to fill it in; after applying you go straight to that map's canvas |
| List page | **Filter** | Fuzzy name filtering, `Apply` takes effect, `Reset` clears; click the title to collapse/expand |
| List page | Table header | Click `Name` / `Width (px)` / `Height (px)` to sort, click again to reverse (Chinese sorts by pinyin) |
| List page | Checkbox | Select all / single select; the bottom shows `N selected` in real time, `Delete` deletes in bulk (with a second confirmation) |
| List page | **Export** | Export the selected (or all, if none selected) maps as JSON, file name `topomap-YYYYMMDD-HHMMSS.json` |
| List page | Name column | Click to enter the canvas, **preview only** (can zoom and pan, cannot modify) |
| List page | Actions → **Properties** | Change the name; canvas size is shown read-only as "Auto (currently W × H)"; the whole map can be deleted in the same dialog |
| List page | Actions → **Edit** | Go directly into that map's edit mode |
| Canvas | **Edit map** | Shows the toolbar: `Map elements: Add/Remove`, `Shapes: Add/Remove`, `Links: Add/Remove`, with `Save` / `Exit editing` on the right |
| Canvas | Zoom | Fixed at **100%** on entry; `−` / `100%` / `+` adjust zoom, `Fit to window` does adaptive zoom; the toolbar shows `Auto · current W × H` |
| Canvas | Add map element | Creates a new component in the center of the canvas and opens its properties window (type defaults to "Image", label "New component") |
| Canvas | Click an element | Opens the "Map element" window: type (image/host), label, label position, host, problem tags, auto-select icon, icon |
| Canvas | Drag an element / shape | Draggable in all four directions: the canvas fills the visible area, the view auto-pans when touching the right/bottom edge, and when going past the left/top edge the whole map is pushed outward and the canvas grows to the top left |
| Canvas | Link Add | Click two elements in sequence → creates a link and opens the "Link" window (label/color/line type + metrics) |
| Canvas | Link metrics | In the "Link" window, **Bandwidth / Inbound traffic / Outbound traffic** each have `Select…` to **multi-select** items from both end hosts; when displayed, the latest values of these items are **summed** and converted by unit, then overlaid at the midpoint of the link (see the next section) |
| Canvas | Remove | After entering remove mode, click the target to delete it (Esc or clicking the button again cancels) |

### Auto discovery (SNMP LLDP / CDP)

Click **Auto discovery** in the page header to open the dialog:

- **Host list**: only hosts with an SNMP interface can be checked; the rest show `No SNMP` and their checkbox is disabled;
  you can search and "Select all with SNMP", and the right side shows `N selected` in real time
- **Discovery depth**: 1~4 levels (default 3), BFS expansion downward from the starting device
- **Protocol**: `LLDP` (on by default) / `CDP` (older Cisco devices) / `EDP` (Extreme devices);
  **Include unmanaged devices** (on by default; when checked, neighbors that cannot be matched to a Zabbix host are also drawn as nodes)
- **Start discovery**: the log area outputs progress line by line; when done, the same button becomes **Generate map**, and a name input appears
  (default `Auto discovery YYYY-MM-DD HH:MM`)
- After generation, layout is by BFS level: `x = 80 + level × 300`, within the same level vertically `y = 80 + row × 170`;
  duplicate names automatically get ` (2)`; when done you go straight to the new map's canvas
- **Link color**: links generated by auto discovery are green `00CC00` in the normal state
- **Multiple links**: the same pair of devices can have **multiple** links (multiple member ports of a link aggregation, stacking interconnects);
  deduplication is by "both-end ports" rather than device pair, so N member ports generate N lines, each bound to its own port trigger;
  bidirectional LLDP discovery (A→B and B→A) reports the same port pair and is merged into one line.
  On the canvas these N lines are **drawn close together as a bundle** and the whole group is treated as **one link**: they share one label box, clicking any one opens the same
  "Link" dialog, and editing and removal apply to the whole group (aligned with aiops)
- **Neighbor naming / address**: if a Zabbix host is matched, use the host's own name and management IP; for unmanaged devices use `sysName`,
  if `sysName` is empty use `chassisId` (terminals / APs / printers only report a MAC), and only if both are missing use the reported IP.
  When the same device echoes the same address to neighbors with several different names (H3C / Huawei `lldpRemManAddr` behavior),
  that address is deemed invalid and discarded
- **Icons selected automatically by type** (aligned with aiops): switch `Switch_(128)`, terminal / PC `Workstation_(128)`,
  server `Server_(96)`, router `Router_(128)`, firewall `Firewall_(128)`, printer `Printer_(128)`;
  the basis is sysDescr / sysName keywords + whether the peer port is a MAC (MAC ⇒ terminal NIC, corresponding to aiops's
  `is_distribution`). On rediscovery, elements in the old map that are still the default `Server_(96)` are switched to the icon for their type,
  while manually changed icons are left alone
- **Automatic trigger association**: at generation time, find the port-status triggers belonging to this link on both ends'
  triggers by "port name + status keywords", and bind them as the native topology map's **link indicators** (`linktriggers`). When such a trigger fires,
  both this module's links and the native topology map turn red `DD0000`; if both ends match, only the source end is used (to avoid doubling the number)

Port ↔ trigger matching rules (consistent with the aiops project's `lldpDiscoveryService`):

| Item | Approach |
|---|---|
| Keywords | The description matches `link down` / `is down` / `has changed status` / `处于关闭状态` / `链路中断` / `接口关闭` / `接口状态变化` / `端口关闭`, etc. |
| Port name | After normalization, match on alphanumeric boundaries: `Gi0/0/6` ↔ `ge0_0_6` ↔ `GigabitEthernet0/0/6` are treated as the same port, and `ge006` will not match `ge0060` |
| Preferred end | The source end (the end scanned in the discovery direction) is preferred; only if the source end has none does it fall back to the target end |

The implementation lives in `services/LldpDiscovery.php` (PHP's `snmp` extension, no net-snmp command line needed):

| Item | Approach |
|---|---|
| Read neighbors | Split-table walk: `lldpRemTable` (`…2.1.4.1.1`), `lldpLocPortId` (`…3.7.1.3`), `lldpLocSysName` (`…3.3.0`), `lldpRemManAddr` (`…4.2.1.3`). **Never walk the whole `1.0.8802.1.1.2.1` tree** (reason in 1.0.10 below) |
| Neighbor port | `lldpRemPortId` (column 7) — switches report interface names like `GigabitEthernet1/0/1`; only when it is a MAC (terminal NIC) does it fall back to `lldpRemPortDesc` (column 8) |
| Local port | `lldpLocPortId` (`3.7.1.3.<port>`) — Huawei's `lldpRemLocalPortNum` is "slot << 16 \| port number" and cannot be used as an ifIndex |
| Local name | `lldpLocSysName` (`3.3.0`) — Huawei's `sysName` is often `(none)` |
| Neighbor address | `lldpRemManAddr` (`4.2.1.3`) is hidden in the index; supports len=4 binary and Huawei's len=11 ASCII dotted decimal |
| EDP neighbors | `EXTREME-EDP-MIB`: neighbor name `1.3.6.1.4.1.1916.1.13.2.1.3`, neighbor port `…2.1.6`; the index is `<local port ifIndex>.<neighbor id>`, and the local port is renamed using IF-MIB `ifName` (EDP does not report an IP) |
| OID output format | Explicitly set to `SNMP_OID_OUTPUT_NUMERIC`: the default mix of `IF-MIB::ifName.7` / `iso.0.8802…` truncates multi-part indexes and parsing will certainly fail |
| Own address | Huawei fills its own management address into all ports; filter it out by local interface IP |
| Match host | Use IP first, then index matching by sysname / host name / visible name; if no match, it is "unmanaged" (image element) |

**Why split into multiple narrow table walks**: early versions, to avoid rate limiting of consecutive requests by Huawei / H3C snmpd, changed to "walk the whole
LLDP-MIB subtree in one go", which ran into a harder problem — the `lldpExtensions` in the subtree (`1.0.8802.1.1.2.1.5`,
i.e. LLDP-MED) makes net-snmp's GETBULK **hang forever** on H3C Comware (measured: still no response after 30s,
and even `timeout -s KILL` could not kill a result), so the whole discovery round was dragged down by this one device and the UI stayed at "Scanning xxx …" forever.
After splitting tables, every table returns stably in milliseconds (remTable 144 rows / 0.53s), and one device takes 4 requests, about 1s.
Rate limiting is still guarded by these constraints:

- Two requests to the same device are at least 70ms apart
- On failure (timeout **or** an empty table returned), retry once each with 350ms / 900ms backoff
- At most 8 devices per batch (3 per batch in the frontend), and the whole batch has a **20s time budget**; hosts over budget are marked as skipped,
  so nginx's `fastcgi_read_timeout` is not exceeded and turned into a 502
- The frontend adds another **45s timeout** to each `discover` request: when it really hangs it reports "Scan timeout (no response after 45 seconds),
  this batch of devices was skipped" instead of letting the dialog spin forever

Error messages come in two kinds, pointing to different causes:

- `SNMP did not respond (timeout or device rate limiting), please try again later` — not a single response (device unreachable / rate limited)
- `No LLDP / CDP / EDP neighbors (the device may not have the neighbor discovery protocol enabled)` — the device answered, but the table has no neighbors

### Link metrics (bandwidth / inbound / outbound traffic)

Each link can carry three sets of items, used to show real-time metrics at the midpoint of the link:

| Item | Approach |
|---|---|
| Selection | In the "Link" window, **Bandwidth / Inbound traffic / Outbound traffic** each have a `Select…` that opens the item selector; **entirely chosen manually by the user**, the module does no automatic matching |
| Available range | Only items of **the hosts at both ends of this link** are listed, and only **numeric** ones (`value_type` 0 float / 3 unsigned integer), filtering out the hundreds of `net.if.alias` / `descr` text items on switch templates; searchable by name or key |
| Multi-select | Each can be multi-selected: multiple member ports of a link aggregation are multiple lines between the same device pair, and each gets its own member port items |
| Value | When displayed, take the **latest value** of each item (i.e. "Latest data" in the item list), **sum** them, then convert by unit |
| Unit conversion | Unit contains `bps` (bits/second) → format directly as `bps / Kbps / Mbps / Gbps`; unit is `Bps` / `B/s` / `bytes` / `octets` (bytes) → multiply by 8 to get bits/second; other units (`%`, `count`…) → display "value + unit" as-is |
| Rendering | Link midpoint top to bottom: label → `≡ Bandwidth` (yellow) → `↑ Inbound` (cyan) → `↓ Outbound` (green); when no data is available show `-` |
| Storage | `sysmaps_links` has only seven columns `linkid/sysmapid/selementid1/selementid2/drawtype/color/label` and no spare columns, so the configuration is stored in the **module-created table** `topomap_link_metrics` (`linkid` primary key + `sysmapid` + `bw_itemids/in_itemids/out_itemids`, values are comma-separated item IDs). The table is lazily created with `CREATE TABLE IF NOT EXISTS` on the get / save paths |
| Claiming linkid after save | `map.update` does not return the `linkid` of new links, so after saving **read the links back once**: existing links match directly by `linkid`, and new links (those without `linkid` in the array) are claimed in order from the read-back result "excluding existing ids, in ascending numeric order" — Zabbix inserts new links in payload array order |

### Link routing (auto orthogonal / manual waypoints)

The "Link" dialog has a new **Routing** section with three modes:

| Mode | Effect |
|---|---|
| Auto curve | Default, same as before (a single line is straight, member lines of a link aggregation are drawn close together as a bundle) |
| Auto orthogonal | Route as horizontal / vertical orthogonal polylines (horizontal-dominant uses "horizontal-vertical-horizontal", vertical-dominant uses "vertical-horizontal-vertical"), good for routing around device icons and text boxes |
| Manual waypoints | Click **Pick waypoints on canvas** to close the dialog and enter waypoint mode: **click on empty space to add a waypoint, drag a dot to adjust, double-click a dot to delete, Esc to finish**, no limit on the number of waypoints |

Multiple member lines of a link aggregation share one routing and set of waypoints as a group (clicking any one opens the same dialog); when the canvas pans or elements are dragged and the content moves as a whole, the waypoints move with it and the detour relationship is unchanged. When the canvas tightens automatically before saving, waypoints are also included in the content extent.

Routing configuration is stored in the module-created table `topomap_link_paths` (`linkid` primary key + `sysmapid` + `routing` + `waypoints` JSON), lazily created like the metrics table; `routing`: `0` auto curve / `1` auto orthogonal / `2` manual waypoints. New links likewise claim `linkid` by `new_seq`; when rediscovery reuses a `linkid`, waypoints are preserved as-is, and the configuration of removed links is cleaned up together with the map.

### View state memory (same as native)

When native `map.view` is called without `sysmapid`, it reads `web.maps.sysmapid` from the user profile,
and the list page `sysmaps.php` calls `CProfile::delete('web.maps.sysmapid')` when opened;
the left menu "Topology map" always points to `action=map.view`, so clicking the menu always returns to the last view.

This module copies that approach:

- Opening a map → `topomap.data&mode=remember` writes `web.topomap.sysmapid`
- Back to the list / browser back → the same endpoint deletes it
- On page load the controller decides `data-sysmapid` by this key, and the frontend enters the canvas or stays on the list accordingly
- The address bar is synced with `&sysmapid=N`, so browser back can go from the canvas back to the list
- When a map is deleted, it automatically falls back to the list and clears the stale record

Because it lives in the database user profile, it is remembered across browsers, devices and re-logins.

### Host status (consistent with the native topology map)

Host elements overlay real-time status during preview; **the colored line of text below the label** follows the same rules as the native topology map:

| Situation | Status line text | Color | Icon |
|---|---|---|---|
| OK | `OK` | `#009900` (`ok_unack_color`) | `iconid_off` |
| Unresolved problem | Only one problem → trigger name; multiple → `N problems` | `#E45959` (`problem_unack_color`) | `iconid_on`, falls back to `iconid_off` if the icon is unavailable |
| Disabled | `Disabled` | `#960000` | `iconid_disabled` + grayed out |
| In maintenance | `In maintenance` | `#EE9600` | `iconid_maintenance` |

Status is only written in the label, **no problem count badge at the top right of the icon** (the native topology map does not have one either). Colors are not hard-coded: the backend reads the global "trigger display options" configuration from
`CSettingsHelper` and only uses Zabbix defaults if unavailable.

What the first line of the label shows is determined by `sysmaps.label_type` (if "advanced labels" is enabled, by `label_type_host` etc.):

- `0` custom label (`selements.label`)
- `1` IP address
- `2` element name (host name)
- `3` status only
- `4` no label
- `5` custom macro template (this module falls back to displaying the label text)

### Canvas size and drag boundaries

Element coordinates are stored in `sysmaps_elements.x/y`, and the canvas size comes from `sysmaps.width/height` —
this is the same data as the native topology map. The difference is that **this module does not let you hard-code this size**:

- **On open**: the canvas takes the larger of "content bounding box (elements + shapes + 60px padding)" and "canvas visible area".
  The canvas always fills the entire visible area, so every point on screen counts as canvas and there is no "this part on the right is not canvas, so it cannot be dragged into".
  A large 3000×3000 canvas stored previously also no longer opens as a screen full of empty grid.
- **Dragging right / down**: dragging beyond the current range grows the canvas in real time (`growCanvasTo`), and **when the pointer touches the canvas edge the view also
  auto-pans** (`autoPan`, 16px per frame within 56px of the edge), so dragging to the window edge does not "get stuck".
- **Dragging left / up**: `x/y` cannot be negative (same data as the native editor), so after reaching 0, `shiftContent()`
  pushes **the whole map down-right**, the dragged piece snaps back to 0, and the view pans in the opposite direction — on screen the elements look unchanged, but in fact **the canvas has
  grown a piece toward the top left**. So there is no boundary in any of the four directions.
- **On window change**: `resize` pads the canvas to the new visible size (only grows, never shrinks, so existing content is not destroyed).
- **On save**: first use `contentMin()` to snap the content as a whole back to the top-left corner (leaving no top-left blank, never writing negative coordinates),
  then tighten to the content but not below the visible area, and write the final width/height back to `sysmaps.width/height`.
- The toolbar's `Auto · 1720 × 856 px` is the current actual size, with a tooltip on hover.
- The canvas is fixed at 100% on entry (1 pixel = 1 pixel); click **Fit to window** when the content is larger than the window.

> Cost: if you intentionally leave a large blank area in the native editor (for example, reserving space for future elements),
> after saving with this module the canvas will be tightened to the content / visible area size. The data is shared, and there is no separate field to store an "auto / manual" flag.

---

---

## Installation

1. Put the entire `topomap/` directory into the Zabbix frontend module directory; the level must be `ui/modules/topomap/`
   (`manifest.json` must be directly in this directory, do not add another nesting level).
2. Make sure the Web user has read permission on the directory:
   ```bash
   chown -R www-data:www-data ui/modules/topomap   # or chmod -R 755
   ```
3. **Administration → General → Modules → Scan directory**, find `Auto Topology Map (自动拓扑图)`, and enable it.
4. Refresh the page; **Auto topology map** appears under **Monitoring** on the left (right after "Topology map").
   You can also access `zabbix.php?action=topomap.view` directly.

> Menu unchanged after replacing `Module.php`? php-fpm's opcache may still be caching the old file;
> run `systemctl reload php-fpm` (the module's `init()` only runs on authenticated requests).

## Permissions

- **View**: user type ≥ User (`USER_TYPE_ZABBIX_USER`); which maps / hosts are visible is determined by the Zabbix permission system.
- **Create / edit / delete**: controlled by the user role's **"Create and edit maps"** (`actions.edit_maps`).
  Without this permission the page is read-only: the create button is disabled and "Properties / Edit" are not shown in the list.

---

## Directory structure

```
topomap/
├── manifest.json              # manifest_version 2.0, two actions: view (page) / data (JSON)
├── Module.php                 # registers the menu item under "Monitoring"
├── actions/
│   ├── TopomapView.php        # page controller (CSRF disabled, GET accessible directly)
│   └── TopomapData.php        # data endpoint: list/get/create/rename/delete/save/icons/hosts
├── views/
│   └── topomap.view.php       # page skeleton (DOM is filled by JS)
├── assets/
│   ├── css/topomap.css        # ships with dark/light color sets (the 7.0 theme uses fixed colors, no CSS variables)
│   └── js/topomap.js          # list / canvas / dragging / three dialogs / saving
├── demo/demo.html             # offline demo page (fake data, icons are inline SVG)
└── test/                      # self-check scripts (not included in the release package)
```

`mode` of `topomap.data`:

| mode | Purpose |
| --- | --- |
| `list` | Map list (name, element count, whether editable) |
| `get` | Single map + elements/links/shapes + host names and problem status |
| `create` / `rename` / `delete` | Create map / rename / delete map |
| `save` | Full save (`payload` is JSON) |
| `icons` | Icon library (`image.get`, imagetype=1) |
| `hosts` | Host selector search |

---

## Known limitations (all intentional, not bugs)

1. **Shares data with the native topology map**: maps created by the module appear under "Monitoring → Topology map", and maps created natively also appear in this list.
   The advantage is one dataset with two entries; if you want complete isolation, you would need to save to a separate table (a large change).
2. **Icon map is not resolved in preview**: when "Auto-select icon" is checked, the property is saved as usual (the native topology map takes the icon by icon map),
   but this module's preview always shows the "default icon"; the status line and problem icon still work normally.
3. **Host group / trigger / nested map elements**: can be previewed, dragged and deleted, but cannot be edited in the properties window (the reason is shown),
   and the status line currently only computes for **host** elements (aggregate status for host groups/triggers is not implemented); to change them, use the native topology map editor.
4. **Label type `5` (custom macro template `label_string_*`) falls back to the label text**: the preview does not resolve macros like `{HOST.NAME}`,
   while native expands them. The other 0/1/2/3/4 are consistent with native.
4. **Canvas size is automatic** (`sysmaps.width/height` is derived from the content and not smaller than the visible area):
   it expands when dragging in any of the four directions, and on save the content is snapped to the top-left and tightened. So there is no "manually enter width/height" entry, and no large blank areas are left.
5. **Saving is a diff update by id**: `map.update` compares `selementid / linkid / sysmap_shapeid` with the database and
   calculates "add / update / delete" itself. The frontend submits the complete state on the canvas, new elements use temporary numeric ids (starting at `900000001`,
   must be purely numeric, otherwise under PostgreSQL `dbConditionId` will directly error when comparing bigint with text), and Zabbix replaces them with real ids when persisting.
   Therefore elements, shapes and links deleted in the module are really deleted — whatever you submit is what you get.
6. **Map names must be unique**: this is a constraint of `sysmaps`. On a duplicate name the module directly reports "A map named … already exists"
   instead of letting Zabbix fail silently (see the measured records below).

## Compatibility

- Developed and verified against **Zabbix 7.0** (7.0.28 source). `manifest_version` 2.0.
- The API fields used (`selements` / `links` / `shapes` of `map.create/update`, `image.get`,
  `hostids` of `problem.get`, `CRoleHelper::ACTIONS_EDIT_MAPS`) were each confirmed against the 7.0.28 source.
- Differences in 6.4 and earlier are mainly in the menu component and controller signatures; `Module.php` / both controllers have existence checks as fallbacks,
  but they are **not tested on 6.x**.

## Self-check

```bash
# Structural self-check without a php binary (bracket balance + key methods exist)
python test/php_check.py

# Style audit: check the [hidden] fallback rule, conflicts between author styles and hidden, and whether JS writes .hidden directly
node   test/style_audit.js

# jsdom smoke test: real view + real JS + fake endpoints, 119 assertions
NODE_PATH=<node_modules containing jsdom> node test/smoke.js

# Regenerate the offline demo page + demo page self-check
python test/build_demo.py
NODE_PATH=<node_modules containing jsdom> node test/demo_check.js

# Real-machine end-to-end (requires access to a Zabbix with this module installed; account/password at the top of the script)
python test/e2e_test.py        # hit backend endpoints directly: create map/save element/save shape/save link/rename/delete, 25 items
node   test/real_e2e.js        # real page + real JS + real backend: create → canvas → edit → save → read back persisted data, 56 items
node   test/canvas_probe.js    # read-only probe: open a live map and print the actual canvas size and zoom (no data changes)
```

> jsdom does not compute CSS, so pure style problems like "the dialog cannot be closed" cannot be caught by the smoke test — those are guarded at the source level by
> `style_audit.js`.

---

## Measured records (Zabbix 7.0.28 / CentOS 8 / nginx + php-fpm 8.2 / PostgreSQL)

Three problems fixed after deployment to a real machine are recorded here to avoid repeating them:

1. **Writing `APP::Component()` in `Module.php` makes the menu silently disappear.**
   Inside `namespace Modules\Topomap`, `APP` is resolved as `Modules\Topomap\APP`;
   PHP normally has a rule to "fall back to the global class if not found", but Zabbix's autoloader throws
   `Error: Class "Modules\Topomap\APP" not found` directly and the fallback does not happen — and the outer layer of `init()` is wrapped in
   try/catch, so the exception is swallowed, which manifests as "the module is installed, the page opens, but there is no menu".
   **You must write `\APP::Component()`** (the function `_()` and constants do not have this problem, only class names do).
2. **Once `output` is specified in `map.get`'s `selectSelements`, `selementid` must be listed explicitly.**
   If it is missing, the elements the frontend receives have no id, and saving inserts existing elements again as new ones.
   Likewise `selectShapes` must include `sysmap_shapeid`, and `selectLinks` must include `linkid`.
3. **7.0.28's `CMapElement::checkLinkInput()` has a case typo**:
   the check is written as `$method == 'updateLink'`, while the actual `__FUNCTION__` = `updateLinks`,
   so "update link" always goes through the "add link" validation branch, requiring every link to have `sysmapid`.
   A link with `linkid` but without `sysmapid` directly reports `Wrong fields for map link.` and the whole map fails to save.
   The module, in `modeSave()`, fills in the current map's `sysmapid` for links with `linkid` to bypass it (while also overriding frontend values to prevent privilege escalation).

Also: the page title must use `CControllerResponseData::setTitle()`; putting `title` into constructor arguments does not work
(`ZBase` only reads `getTitle()`), otherwise the browser tab only shows the site name.

### Fixed in 1.0.2: dialog smeared over the page on entry, "Close" does nothing

**Symptom**: clicking the menu to enter the page shows the "Select host" dialog directly on screen; clicking × or "Close" does nothing.

**Cause**: the whole page's show/hide relied on `el.hidden = true/false`, and the hiding effect of the `hidden` attribute comes from the browser's
user-agent style `[hidden] { display: none }`. In this module's CSS, `.tm-overlay` and `.tm-editbar`
set `display: flex` — author styles have **higher** priority than user-agent styles, so `display: flex` overrides
`display: none`, and the `hidden` attribute is as good as not written:

| Element | Overridden by |
|---|---|
| `#tm-overlay` (properties window) | `.tm-overlay { display: flex }` |
| `#tm-host-overlay` (host selector) | `.tm-overlay { display: flex }` |
| `#tm-editbar` (edit toolbar) | `.tm-editbar { display: flex }` |

Both dialogs were covered; the host selector later in the DOM was on top, so what you saw was "Select host". Clicking Close only
set the attribute back to true, and nothing changed visually.

**Fix (two-pronged)**:

1. Add a fallback rule in CSS, scoped to the module so it does not pollute the native Zabbix UI:
   ```css
   #topomap-root [hidden] { display: none !important; }
   ```
2. In JS, all show/hide goes through `setHidden(el, hide)`, writing both the `hidden` attribute and the **inline** `style.display`.
   Inline styles have higher priority than any author selector, so it works even if the browser caches the old CSS.

**Lesson**: the `hidden` attribute + a class name that has its own `display` = a pitfall you will definitely hit; jsdom does not compute styles,
so the smoke test cannot catch it, hence `test/style_audit.js` was added to intercept at the source level.

---

### 1.0.3: List page aligned with native + canvas 100% + resizable size

**UI**: rebuilt the list page following `include/views/monitoring.sysmap.list.php`, directly replicating the native structure
(`header.header-title` + `filter-space` + `list-table` + `action-buttons`), driven only by its own JS.
Also added name filtering, table header sorting, select all/bulk delete, and JSON export.

**Canvas**: no longer auto-zooms on entry (previously `fitView()` was called in `openMap()`), now fixed at 100%;
the `100%` button is redefined as "back to 100%", and a new "Fit to window" button is added. Width and height can be changed in the create dialog and "Properties".

Three pitfalls encountered:

1. **`layout.htmlpage` only outputs `<div class="wrapper">` + `main_block`, and does not produce `<header>` / `<main>`.**
   So the module page originally had no title bar or top-right button area. The page header and `<main>` must be written in the view, and styles are taken over by the theme's
   `header{}` / `main{}` rules (`header` is 45px with a white background, `.header-controls` uses `margin-left:auto` to align right).
2. **`getInput()` only recognizes fields declared in `validateInput()`.** When adding `width` / `height` / `sysmapids`,
   they were not added to the whitelist, so all of them got default values: the size was always 1000×700 when creating, bulk delete reported "missing valid map ID",
   and export exported all maps. **Adding a parameter to the controller must be accompanied by updating `validateInput`** (this pitfall is very quiet and does not error).
3. **7.0.28's `map.create` returns `false` on a duplicate name instead of throwing.** After the module got `false`,
   `(string) $result['sysmapids'][0]` produced an empty string, and only then did the frontend `openMap('')` report "missing valid map ID",
   completely hiding the real failure reason (duplicate name). Now `modeCreate()` first checks for the same name and gives a clear message, then falls back to "no ID returned".

---

### 1.0.4: Automatic canvas size + canvas area fills the window

**Problem**: the map had only two elements, but the canvas was 3000×3000, and a large blank area remained below the canvas area.

**Cause**: both places were hard-coded.

1. `.tm-canvas-wrap { height: calc(100vh - 235px) }` — 235px was an estimate.
   The header 45 + main padding 20 + toolbar 40 + footer often measured less than 235,
   so the canvas area did not reach the bottom and pure background color was below.
2. Canvas width/height came from `sysmaps.width/height`, hard-coded to 1000×700 when created, and could be manually set to 3000×3000 in properties.
   Once the size was large, all elements crowded into the top-left corner and the rest was empty grid.

**Fix**:

- Container height changed to be measured by `layoutCanvasWrap()`: `viewport height − container top offset − footer height − 12`,
  calculated once when opening the canvas, switching edit mode, and on `resize`, written as inline `height`.
  CSS keeps only `calc(100vh - 200px)` + `min-height` as fallback.

- Canvas size became content-driven:
  - `contentExtent()` takes the bottom-right corner of all elements (including icon size and label height) and shapes;
  - `growCanvasTo()` only expands right/down when needed during dragging / adding, with no hard boundary;
  - `autoSizeCanvas()` tightens to `content + 60px` when opening and saving, with a lower bound of 1000×700;
  - `saveMap()` submits the computed `width/height`, and the backend `modeSave()` clamps it to 100–10000 before writing to the database.
- The width/height inputs in the create dialog and the "Properties" dialog were removed, replaced by a read-only "Auto (currently 1200 × 800 px)".
- The toolbar became `Auto · 1200 × 800 px`.

**Note**: `sysmaps` has no field for "automatic / manual size", so this "automatic" is computed each time the map is opened/saved,
without persisting any flag. Blank space left in the native editor will be tightened after saving with this module.

---

### 1.0.5: Canvas fills the visible area + auto-pan when dragging to the edge

**Problem** (user feedback): "Dragging hosts is still limited by the canvas height and width".

**Cause**: 1.0.4 only implemented "grow when dragging right/down", but the canvas **lower bound was still 1000×700**.
With a 1600×900 viewport the canvas was only 1000×700, and a large area on the right and bottom did not belong to the canvas at all;
dragging to 1000 made the element stop, and although it would expand further out, `.tm-canvas-wrap` is `overflow: hidden`,
so the element immediately ran out of the viewport and was invisible — the feel was "stuck by the canvas width and height".

**Fix**:

- `canvasMinSize()`: the canvas lower bound changed from hard-coded 1000×700 to `max(1000, canvas visible area)`,
  so the canvas always fills the visible area. Every position on screen is inside the canvas, and the boundary falls somewhere invisible.
- `ensureCanvasFitsViewport()`: pad to the new visible size when opening the canvas, switching edit state, and on `resize` (only grows).
- `autoSizeCanvas()` also uses `max(content + 60, visible area)` when tightening, so the saved size is never smaller than the visible area.
- **Dragging changed to map coordinates + edge auto-pan**:
  - `pointerToMap()` derives map coordinates from the current `panX/panY/zoom`, and `grabX/grabY` remembers the grab point,
    so the element does not leave the pointer when zooming or panning;
  - `autoPan()`: when the pointer enters within 56px of the edge, each `mousemove` pans the view 16px and the element keeps moving,
    without releasing and dragging the blank area; the left/top direction only recovers when already out of the viewport (`panX/panY < 0`),
    ensuring the top-left of the canvas never leaves the top-left of the viewport.
  - Element / shape dragging share this logic.
- When the canvas layout is not ready yet (`rect` width/height less than the edge threshold), `autoPan()` returns directly,
  otherwise an environment without layout such as jsdom would drift in one direction forever.

**Verification**: `smoke.js` added 6 items (canvas fills 1400×700, element dragged to x>1000 / y>400,
negative panning appears in `transform` when touching the edge, clicking `100%` resets); `real_e2e.js` set the viewport to 1200×758 on a real page,
confirming that both the stage size and the persisted size became 1200×758.

---

### 1.0.6: Dragging left and up also works (whole-map extrapolation)

**Problem** (user feedback): "It is limited to the left and up". 1.0.5 solved the right and bottom, but `x/y` stopped at 0 —
left/up was still a hard boundary.

**Difficulty**: `sysmaps_elements.x/y` cannot be negative (the native editor does not allow it either, and the native view also reads this data).
So coordinates cannot simply be made negative — that would move elements outside the canvas and make them invisible in the native view.

**Fix**: `shiftContent(dx, dy)`

- When dragging to `nx < 0` (or `ny < 0`), add `(dx, dy)` to the coordinates of **all elements and shapes**, and increase the canvas width/height by the same amount;
- At the same time `panX -= dx * zoom`, `panY -= dy * zoom`, panning the view in the opposite direction — what was originally drawn on screen **stays in place**,
  the effect is "the canvas grows a piece toward the top left", and the pointer can keep dragging outward;
- The element being dragged is separately assigned `x = 0` (snapped back to the boundary); what is pushed away is **the other** content, so it looks like it stopped at the top-left of the canvas;
- The original "left/top edge recovery" in `autoPan()` was removed — that logic fought with the panning.

Supporting changes:

- `drawShapes()` made idempotent (clear `.tm-shape` / `.tm-shape-text` first, then draw),
  so `shiftContent()` can redraw shapes separately without going through `paintCanvas()` to rebuild element DOM (otherwise node references during dragging would become invalid);
- Added `syncElementNodes()`: only changes the `left/top` of `.tm-el`, preserving drag references;
- Before saving, use `contentMin()` to snap the content **as a whole back to the top-left** (`se.x -= minX`),
  otherwise a blank area would remain on the left after dragging left/up; at the same time `pan` is compensated in reverse so the picture does not jump;
- The final persisted coordinates are always `>= 0`, so the native editor opens them perfectly.

**Verification**: `smoke.js` added 10 items (after dragging left/up to -3000 the canvas grows, elements snap to 0, other elements shift right as a whole,
negative panning appears in `transform`, the save payload has min x/min y = 0 and all non-negative);
`real_e2e.js` added 4 items: after dragging to negative coordinates on a real machine, the persisted size is ≥ the visible area and element coordinates are non-negative.

---

### 1.0.7: Host status line (OK / problem / disabled / in maintenance)

**Problem** (user feedback): "Is there no host status? Zabbix's built-in topology map preview shows OK, and also shows when there is a problem."

**Cause**: only icons and badges were implemented, **the second line of text in the native label was not**. Only after capturing the rendering data of native `map.view` was it clear:

```json
"label": [{"content": "新的组件"}, {"content": "正常", "attributes": {"fill": "#009900"}}]
```

Native is "first line label + second line status", and the status text is generated by the rules of `getSelementInfo()` (`ui/include/maps.inc.php` in 7.0.28);
we only drew the first line, so "OK / problem" was never visible.

**Fix**:

- Backend `collectHosts()` extended: `ip`, `maintenance_status`, `unack` (unacknowledged count),
  `title` (name of the most severe problem); `problem.get` adds `name` / `acknowledged`.
- Backend `labelColors()`: reads `ok_unack_color / ok_ack_color /
  problem_unack_color / problem_ack_color` from `CSettingsHelper`, using Zabbix defaults if unavailable;
  disabled `960000` and maintenance `EE9600` are fixed colors (consistent with `getSelementInfo()`).
- Backend `modeGet` retrieves more: `label_format / label_type* / label_string* / show_unack / expandproblem`.
- Frontend `elementStatusLines()` + `problemLines()`: fully replicate the combination of `show_unack × expandproblem`
  (a single problem shows the title, multiple show the count, unacknowledged-only shows "N unacknowledged problems"…).
- Frontend `labelTypeFor()`: when `label_format=1`, take `label_type_host` etc. by element type, otherwise use the global one;
  `elementLabel()` returns label text / IP / name / empty / empty for 0/1/2/3/4 respectively.
- Rendering changed to a container + multiple lines: `.tm-el-label > .tm-el-line`, with the status line colored inline via `.tm-el-status`;
  the label height in `contentExtent()` changed to `line count × 18`.

**Verification**: temporary real-machine map (create map → render → delete, `test/problem_map_probe.py`):

```
#406 机房办公交换机 / "2 problems" (rgb(228,89,89))   ← has problems, red
#407 image (no status line)                            ← image element
#408 3楼海康录像机 / "OK" (rgb(0,153,0))               ← OK, green
```

The map "网" (`label_type=0`) now shows `新的组件 / 正常`, exactly consistent with native's `[{新的组件},{正常}]`.
`smoke.js` +9 items (problem count and red, OK green, image has no status line, new host-type element has a status line), `demo_check.js` 17 items.

---

### 1.0.8: Single problem writes the trigger name + preview UI cleanup + selecting a host auto-fills name/IP

**User feedback** (five points): a problem only shows the count, so you cannot see which trigger; the problem count at the top right of the icon is redundant; preview mode should not have the canvas grid;
the canvas page should not have "Create map" at the top right; after selecting a host it should directly show the host name and IP.

**Fix**:

- **Problem line**: as long as an element has **only 1 problem**, write the trigger name (`se._title`, the `problem.name` of the most severe one);
  only when there is more than 1 does it decide between count / count + most severe title based on the map's `expandproblem`.
  This is **the only place deliberately deviating from native**: measured on the map "网" with `expandproblem=1`, native writes only
  "1 problem" for a single problem, so you cannot tell which trigger it is, and the user wanted the name.
  Also removed `recent: true` from `problem.get` (it counts recently resolved problems too, inflating the problem count).
- **Remove the badge**: no longer generate `.tm-el-badge` (native does not put a problem count at the top right of the icon); problem information only goes in the label line.
- **Grid only in edit mode**: `.tm-canvas-wrap` is a solid color by default, and the grid rule is scoped under `#topomap-root.tm-editing`;
  `setEditing()` already toggles the `tm-editing` class, and `test/style_audit.js` adds a static check to guard it.
- **Hide the create button on the canvas page**: `openMap()` hides `.header-controls`, and `doBackToList()` restores it.
- **Selecting a host auto-fills the label**: `modeHosts` returns the main interface IP (`hostIp()` prefers `main=1`),
  the host selector shows `name + IP`; after selecting a host in the dialog, if the label is empty / still the "New component" placeholder,
  it is automatically written as two lines "host name" + "IP" (if the user manually changed the label it is not overwritten). Label rendering supports `\n` line breaks,
  and `labelLineCount()` computes the bounding box by the actual line count. When changing hosts, `_status/_problems/_unack/_title` are refreshed together,
  and the problem count of the old host is not carried over.

**Real-machine verification** (`test/status_render_probe.js`):

```
#414 新的组件 / [网络设备]端口Gi0_0_33()(ge0_0_33): 处于关闭状态   (rgb(228,89,89))  ← single problem → trigger name
#413 新的组件 / 2 个问题                                            (rgb(228,89,89))  ← multiple problems → count
#385 新的组件 / 正常                                                (rgb(0,153,0))
"Create map" at top right hidden: true
hosts endpoint: {"name":"电工房办公交换机","ip":"192.168.40.115","status":0} …
```

`smoke.js` 119 items (added: no badge, single problem writes the trigger name, canvas page hides the create button, selecting a host auto-fills a two-line label),
`demo_check.js` 19 items, `style_audit.js` 8 items (added: grid only in edit mode), `real_e2e.js` 56 items.

---

### 1.0.9: Auto-discovery links green normally / red on alarm + automatic port status trigger association

**User feedback**: change auto-discovery link colors to normal green `00CC00` and alarm red `DD0000`, and associate triggers automatically.

**Approach** (use Zabbix's native "link indicator" mechanism, do not reinvent the wheel):

- **Normal green**: links created by `modeDiscoverSave` have `color = 00CC00` (previously gray `5B7C8D`).
- **Automatic association**: at generation time, match triggers on the hosts at both ends of the link by "port name + status keywords"
  (`matchLinkTriggers`, source end first, and if matched only the source end is used), and write the matches into the link's
  `linktriggers: [{triggerid, drawtype: 0, color: 'DD0000'}]`.
  This way, when the **native topology map** (Monitoring → Topology map) is opened, it also draws the line red based on trigger status.
- **The self-drawn canvas shows it too**: `modeGet` reads back the binding relationship, then queries `problem.get` again to mark which triggers are
  firing (`problem: true`); `drawLinks()` in `topomap.js` draws the line in the trigger color accordingly
  (`linkProblemColor()`), otherwise uses the link's own color.
- **Not lost on save**: `sanitizeLinks` passes `linktriggers` through, and the frontend save payload writes it back as-is,
  preventing a normal save (`map.update`) from washing away the binding relationship.

**Real-machine verification** (Zabbix 7.0.28, measured after deploying locally to a test machine):

```
map.create links[].linktriggers        -> persisted successfully
map.update links[] (must include sysmapid)    -> linktriggers preserved
map.update links[] (without sysmapid)    -> "Wrong fields for map link." (old 7.0.28 bug, module already avoids it)
```

**Pitfall (measured with 7.0.28's CMap.php)**: `linktriggers` is a **nested field of `selectLinks`**, not a separate
`selectLinkTriggers` option — in the source it is exactly `outputIsRequested('linktriggers', $options['selectLinks'])`.
So the `selectLinks` of `modeGet` can only be given `'extend'`: a hard-coded field list would be treated as columns of `sysmaps_links`, returning empty (or an unknown field error), and
`selectLinkTriggers` does not work at all in this version.

**Three verifications**:

1. Real-machine end-to-end (`discover_e2e.py 10764`): all 3 links are `00CC00` and all carry `linktriggers`,
   automatically matching 2 port status triggers (`ge0_0_23` / `ge0_0_24`, one each, with "处于关闭状态" in the description).
2. `problem: true` path: bind a **currently firing** trigger to a temporary map; the module's `mode=get` correctly returns
   `problem: true` (false when not firing).
3. Browser visual check (`zabbix.php?action=topomap.view&sysmapid=...`): the alarming link is drawn `#DD0000`,
   normal links `#00CC00`, no console errors.

`smoke.js` added 3 assertions: an alarming link is drawn `#DD0000`, a newly created link without a trigger keeps its own color, and discovery links carry source/target ports.

---

### 1.0.10: Fix "cannot discover a single device / spins for a long time" + EDP support + unmanaged devices

**User feedback**: `Start discovery: 1 starting point, depth 2, protocol LLDP`, scanning "机房办公交换机" for a long time and no device appeared;
asked to add EDP; "Include unmanaged devices" checked but had no effect.

**Root cause** (measured by running a separate process per OID): `scanLldp` at the time did "walk the whole `1.0.8802.1.1.2.1` in one go",
and the `lldpExtensions` (`1.0.8802.1.1.2.1.5`, LLDP-MED) under that subtree makes this H3C S5560S (Comware 7)
agent **hang forever** under GETBULK:

| Approach | Result |
|---|---|
| `snmp2_real_walk('1.0.8802.1.1.2.1')` (what the module did then) | **Hangs**, `timeout -s KILL 30` cannot produce a result |
| `snmp2_real_walk / snmp2_walk` on each narrow table | 53–527ms, all normal (remTable 144 rows / 0.53s) |
| `snmpwalk / snmprealwalk` (PHP's v1 GETNEXT variants) | The device does not answer SNMPv1 at all and always returns false after the timeout |

The 20s budget of `modeDiscover` was only checked "before scanning the next host", so it could not stop a walk stuck in a C call →
the whole request was dragged down, and the UI stayed at "Scanning 机房办公交换机 …".

**Fix**:

1. `scanLldp` changed to split-table walks (only the four narrow tables remTable / locPortId / locSysName / manAddr),
   and aligned column numbers with the standard `lldpRemEntry`: the original self-made mapping was off by one overall, and `chassis` got
   `lldpRemPortIdSubtype`, an integer; the neighbor port also switched to `lldpRemPortId`, falling back to `lldpRemPortDesc` only when it is a MAC (terminal NIC).
2. Explicit `snmp_set_oid_output_format(SNMP_OID_OUTPUT_NUMERIC)`: the default output mix of
   `IF-MIB::ifName.7` / `iso.0.8802…` truncates multi-part indexes (`<ifIndex>.<neighbor id>` of EDP), and parsing certainly fails.
3. Added **EDP**: `EXTREME-EDP-MIB extremeEdpNeighborTable` (`1.3.6.1.4.1.1916.1.13.2.1`),
   neighbor name from column 3, neighbor port from column 6, and the local port is converted from the ifIndex in the index to the interface name using IF-MIB `ifName`;
   the dialog's protocol section adds an EDP checkbox, and the backend protocol whitelist adds `EDP`.
4. The frontend adds a 45s timeout to each batch of `discover` requests (`withTimeout`); when it really hangs it reports "scan timeout" instead of spinning forever.

**Real-machine verification** (the same "机房办公交换机", hostid 10762 / 192.168.40.253 / SNMPv2c):

```
single host, LLDP+CDP+EDP                    -> 0.94s, 11 neighbors (previously hung forever)
5 together (10762/10763/10764/10766/10773)   -> 16.4s, all 5 returned neighbors (11/29/5/12/4)
discover_e2e.py 10762                     -> all passed (links 00CC00, automatically bound 4 port status triggers)
discover_e2e.py 10764                     -> all passed (regression: 3 links / 3 triggers)
browser with EDP + include unmanaged devices -> about 5s, log
     "Discovery complete: 31 devices, 30 links, of which 20 are already in Zabbix and 11 are unmanaged devices", no console errors
```

**Why "Include unmanaged devices" previously "did nothing"**: the switch itself is fine (when checked, neighbors that cannot be matched to a Zabbix host
are added to the map as image elements), but the backend hung first and did not read a single neighbor, so naturally nothing could be drawn. After fixing the hang, the browser
measured 11 unmanaged devices in one try.

---

### 1.0.11: Multiple links between the same device pair + selectable bandwidth/inbound/outbound items per link

**Requirement**: with link aggregation, multiple member ports connect to **the same host**, and multiple links should be generated; each link should be able to show
**bandwidth / inbound and outbound traffic**. The data source is chosen by the user from Zabbix item latest values, with no automatic matching.

**Three gaps in the approach** (compared with `f:\bbb\aiops`):

1. **Link deduplication key**: previously both frontend and backend deduplicated by "device pair", allowing only one line between the same pair of devices → changed to deduplicate by
   an unordered key of "**both-end ports**" (`device#port` sorted on both ends and concatenated). This way N aggregation member ports generate N lines,
   while bidirectional LLDP discovery reports the same port pair and is still merged into one line; when ports are empty it degrades to a device pair, consistent with the old behavior.
2. **Metric storage**: `sysmaps_links` has only seven columns and no spare columns, so a new module-created table `topomap_link_metrics`
   was added (`linkid` primary key / `sysmapid` / `bw_itemids` / `in_itemids` / `out_itemids`),
   lazily created with `CREATE TABLE IF NOT EXISTS` on the get / save paths.
3. **Claiming linkid after save**: `map.update` does not return the `linkid` of new links, so after saving **read the links back** once:
   existing links match directly by `linkid`, and new links use the `new_seq` given by the frontend (their order of appearance in the links array)
   to claim in order from "the read-back result excluding existing ids, ascending numeric", then write the metrics table.

**Frontend**: the "Link" window adds three rows **Bandwidth / Inbound traffic / Outbound traffic**, each with `Select…` / `Clear`;
`Select…` opens the item selector (only **numeric** items of **the hosts at both ends of this link**, searchable, multi-selectable);
the link midpoint on the canvas is drawn top to bottom: label → `≡ Bandwidth` (yellow) → `↑ Inbound` (cyan) → `↓ Outbound` (green),
the value is the **sum of the latest values** of the selected items, converted by three unit categories: `bps` / bytes / other.

**New backend endpoint**: `mode=items` (given a batch of hostids + a keyword, returns numeric items and their latest values).

---

### 1.0.12: Local port name fallback + offset multiple links between the same element pair + selectable trigger source end

**Requirement (real-machine feedback)**: ① the local port of the core switch is shown as a number like `65547` on the link, so multiple lines between the same pair
of devices cannot be merged and triggers are associated with the wrong end; ② multiple link aggregation lines completely overlap on the canvas and "only 1 is visible"; ③ the link properties should allow selecting the "trigger source end".

**Root cause**: `lldpRemLocalPortNum` on Comware is "**slot << 16 | port number**", and this module originally relied on
`lldpLocPortId` to get a readable name; but on the busiest core switch (29 neighbors, previous walks had already rate-limited snmpd)
this step returned an **empty table**, so the local port degraded to the raw number. The raw number and the peer-reported `Gi1/0/11` are not equal after normalization,
so bidirectional duplicate lines could not be merged (visually overlapping into one), and matching triggers by port could not find the source end → fell back to the peer.

**Approach**:

1. **Local port name fallback** (`LldpDiscovery`): an empty `lldpLocPortId` table must be **retested once** (the device returns
   an empty table when rate limited); if still unavailable, walk `IF-MIB ifName` on demand once (treating the Comware-encoded port number as an ifIndex);
   only if that fails, decode `slot<<16|port` into `GigabitEthernet{slot}/0/{port}`; only as a last resort fall back to the raw number.
2. **Deduplication key port normalization** (same rule for frontend/backend `ifPortKey`): `Gi1/0/11` / `GigabitEthernet1/0/11` /
   `ge1_0_11` all normalize to `ge1011`, so bidirectional duplicates merge and LAG multiple member ports each become their own line.
3. **Draw multiple links between the same element pair offset** (`drawLinks`): group by "element pair", give each line in the group a symmetric vertical
   offset, and draw as a **quadratic Bezier curve** (a single line has offset 0 = still a straight line); the label falls at the curve midpoint and no longer completely overlaps.
4. **Selectable trigger source end**: link properties add a "**Trigger source**" dropdown (Auto (source end first) / Source end only /
   Target end only / Associate both ends / No association), persisted with the link (the module-created table adds `source_port` / `target_port` /
   `trigger_source` three columns, and the old table is automatically `ALTER`ed). After changing it and clicking "Apply", `mode=linkTriggers` is called
   to re-match immediately by the new criteria; **changing only the label/color will not touch triggers**, avoiding washing away manually bound triggers.
5. **Annotate both-end ports on the link**: the topmost line at the link midpoint draws `source port → target port` (9px gray text),
   used to distinguish multiple member ports of the same device pair in link aggregation.

**New backend endpoint**: `mode=linkTriggers` (given both-end hostids + ports + `trigger_source`, returns matched port status triggers).

**Troubleshooting record**: the user-reported "bandwidth/traffic selection does nothing" **could not be reproduced** in browser testing (the item selector opened normally,
searching `icmp` returned 6 rows, no console errors). It was judged to be caused by multiple duplicate lines completely overlapping so that "you cannot tell which one you changed";
fix #3 should make it disappear as well.

### 1.0.13: Item selector aligned with native dialog + links close into a bundle + metrics drawn inside the box

**Requirement (real-machine feedback)**: ① the dialog for choosing bandwidth/inbound/outbound items should implement Zabbix's native three-level selection:
"Item" (host edit box + Select) → "Host" (host group + Select) → "Host group" (search + list with checkboxes),
and each row in the item list should have a **checkbox**, while **keeping the search box**; hosts should **prefer the source-end host by default**.
② multiple link aggregation lines are "too far apart" and should be close together as a bundle; ③ bandwidth/traffic values should be drawn **inside the box**; ④ text on links should be **uniformly black**.

**Approach**:

1. **Three-level selector rebuild** (`views/topomap.view.php` + `topomap.js` + `topomap.css`):
   - "Item" dialog: **Host** edit box (chip list, each removable with ×) + `Select` button, **Filter** search box,
     table columns `Checkbox | Name | Key | Type | Information type | Status` (header checkbox select all/half select), bottom `Select / Cancel`.
   - "Host" dialog: **Host group** edit box + `Select` → opens the "Host group" dialog; below is a host list with checkboxes
     (name + IP), and another search box can filter it.
   - "Host group" dialog: search box + group list with checkboxes.
   - **Prefer the source-end host by default**: when opening the item dialog, the host box is automatically filled with the host corresponding to `selementid1`; if the source end is not a host, fall back to the target end.
   - Empty state follows native: funnel icon + `No filter set` / `Use filter to show results`.
2. **New backend endpoints**: `mode=hostgroups` (host group list, searchable by name); `mode=hosts` adds `hostids` /
   `groupids` filtering (`hostids` is used to echo selected host names, `groupids` is used to filter the host list by group);
   `mode=items` adds `type` (type) / `status` (status) columns. `checkInput()` whitelist adds `groupids`.
3. **Links close into a bundle**: `LINK_PAIR_SPACING` reduced from 30 to **12** (aligned with aiops), so multiple lines of the same element pair almost merge into one strand.
4. **Metrics drawn inside the box + all black**: the link label / port / bandwidth / inbound / outbound are drawn in a **rounded rectangle box** (`<rect>` + `<text>`),
   text uniformly `#000000`, and the box background takes a light color by theme (light theme `#ffffff`, dark theme `#f4f7f8`), ensuring black text is legible in the dark theme too.
   Box positions are **offset along the curve** (the i-th falls at t=(i+1)/(N+1), a single line at t=0.5), so adjacent boxes do not overlap even when the lines are bundled.

### 1.0.14: Fix auto-discovery IP / host names + remove link port annotations + aggregation links editable as a group

**Requirement (real-machine feedback)**: ① in auto-discovery maps "several IPs and host names are wrong" (multiple devices are shown as the same
`192.168.1.1`, and a bunch of nodes have only an IP and no name); ② the "source port → target port" text on links is not needed;
③ multiple link aggregation lines **do not need to be individually editable**, they should be treated as one link as a group like aiops.

**Approach**:

1. **IP / name fix** (`services/LldpDiscovery.php` + `actions/TopomapData.php`):
   - `lldpRemManAddr` changed to associate the address by the **full `(timeMark, local port, remIndex)` index** (previously only by local port,
     so multiple rem entries under the same port overwrote each other and addresses were mixed up); if unavailable, fall back to by port.
   - **When a Zabbix host is matched, show the host's own management IP** (`ip` added in `discoveryIndex()`),
     instead of the address reported by the LLDP neighbor — H3C echoes its own management address into `lldpRemManAddr` for all ports,
     which is exactly the root cause of "a bunch of devices all show `192.168.1.1`"; also look up the host name by `sysName`.
   - **Discard "address echoes"**: among the neighbors reported by the same device, if the same IP appears on neighbors with **multiple different device names**,
     it means this is not a neighbor address (an IP is the unique identifier of a device), and the whole batch is deemed invalid.
   - **Discard nameless neighbors**: entries that match no host and have no `sysName` no longer generate nodes (previously they degraded into
     "name = IP" ghost nodes, duplicated with managed nodes).
2. **Links no longer annotate ports**: remove the `source port → target port` line from the link label box (`tm-link-port`);
   port information is kept only in the "Link" dialog, listing **all member ports** for aggregations.
3. **Multiple aggregation links = one editable link** (aligned with aiops): all links between the same element pair are grouped by `linkFamily()` —
   lines are still drawn as multiple bundled curves by member port, but **the whole group shares one label box** (drawn on the middle line,
   and separately overlaid in the badge layer so that later-drawn member lines do not pass through the middle of the box); **clicking any one opens the same "Link" dialog**,
   and label / color / line type / trigger source **change the whole group at once**; metrics are attached only to the first line in the group (shared by the whole group,
   still summed by member port when displayed); in remove mode, clicking any one deletes the whole group.

### 1.0.15: Manually bind triggers to links + host status aligned with the native topology map

**Requirement (real-machine feedback)**: ① triggers can currently only be associated automatically, and automatic association matches **by keywords**; manually created links
(such as a newly added computer connected to a switch) have no LLDP port information and cannot `Select` a trigger — they need to be **selected manually** like bandwidth / traffic,
and there should be a **Clear** button; ② when a host has an alarm, the display should look like Zabbix's built-in topology map (status + icon highlighting).

**Approach**:

1. **"Link" dialog adds a "Triggers" row**: bound triggers are shown as chips (with × to remove individually),
   with `Select…` / `Clear` on the right exactly like the bandwidth / traffic rows. The selector (new `mode=hostTriggers`) lists triggers
   **on the hosts at both ends of the link**: name / severity (color block + Chinese) / status / host, supporting keyword filtering,
   select all, checkboxes. After selection it is written into `link.linktriggers` (color uses the link trigger default `DD0000`),
   and saving goes through the existing write-back path of `sanitizeLinks()`; **once manually selected (Select / Clear / × on a chip) it is no longer overwritten by automatic matching**,
   and only actually changing "Trigger source" triggers a keyword re-match. If a manually selected trigger is firing,
   the canvas immediately draws the line in the trigger color (no need to wait for refresh).
2. **"Clear" must persist**: `sanitizeLinks()` previously wrote `linktriggers` only when non-empty, so an empty array was dropped;
   now **an empty array is written too**, and `CMap::update` deletes the existing bindings in the database accordingly (otherwise clearing would only look empty in the UI).
3. **Host status aligned with native**:
   - The "in maintenance" icon (`iconid_maintenance`) was missing — added, with the order consistent with native: disabled → problem → maintenance.
   - Added the native topology map's **element highlighting** (`sysmaps.highlight`, returned by `mode=get`): when the switch is on,
     **draw a severity-colored circle behind icons with problems** (0 unclassified `#97AAB3` / 1 information `#7499FF` / 2 warning `#FFC859` /
     3 average `#FFA059` / 4 high `#E97659` / 5 disaster `#E45959`), and **acknowledged** problems add a 4px green outline `#329632`;
     **in maintenance** draws a `#FF9933` semi-transparent box, **disabled** draws a `#999999` semi-transparent box — all implemented with the sizes and colors of native
     `getMapHighligts()` / `SVGMapElement.updateHighlight()`.

### 1.0.16: Canvas auto-refresh + three-row trigger filtering + editable keywords + tree layout

**Requirement (real-machine feedback)**: ① no refresh during status testing: when a trigger fires the link does not turn red, and bandwidth / traffic stay at one value;
② the trigger selector should be like the bandwidth and traffic item selector, with a **host group edit box + Select** and a **host edit box + Select**;
③ the default color of manual links `5B7C8D` should change to `00CC00`; ④ the keywords for automatic trigger association should be **editable** in the "Auto discover topology" window
(a large multi-line text box, one per line, default "处于关闭状态"); ⑤ the automatically generated topology map should use a **tree layout** like aiops.

**Approach**:

1. **Canvas auto-refresh**: after entering the canvas, silently `mode=get` and redraw every 20 seconds (skipped in edit mode / when the page is hidden,
   preserving zoom, pan and current selection). `mode=get` already recomputes `linktriggers[].problem`
   and the `lastvalue` of metrics every time, so trigger color changes and bandwidth / traffic values follow.
2. **Three-row trigger selector filtering**: add a "Host group" edit box + Select, a "Host" edit box + Select,
   sharing the host / host group dialogs with the item selector; `mode=hostTriggers` adds `groupids`,
   narrowing hosts by group before listing triggers. Still pre-fills the hosts at both ends of the link by default.
3. **Manual link default color** `00CC00` (used when creating a link without a bound trigger).
4. **Editable keywords**: the module adds a settings table `topomap_settings` and `mode=settings / saveSettings`;
   the "Auto discover topology" window adds a multi-line text box (default "处于关闭状态", one per line), which is submitted with the payload
   and persisted when generating the map; both automatic association and re-matching in link properties use these keywords.
5. **Tree layout**: `modeDiscoverSave` uses aiops's tidy tree to compute coordinates — the root at the top,
   children in a horizontal row, parents centered above their children, leaf slot width 190, level spacing 180.

### 1.0.17: Highlight changed to a "perfect circle" + links above highlights + 1-minute refresh interval + icons no longer flicker

**Requirement (real-machine feedback)**: ① in native, status highlighting is a **circle** and links are drawn directly over host icons; in the module the highlight is
**elliptical** and links stop at the ellipse edge; ② change the 20-second refresh to **1 minute**; ③ every redraw all host icons **flicker**, while native does not.

**Approach**:

1. **Highlight moved to the SVG layer, drawing a perfect circle**: no longer using CSS (`width/height: calc(100% + 20px)` + `border-radius: 50%`,
   which becomes an ellipse when the icon is not square), but consistent with native `SVGMapElement.updateHighlight()`:
   an independent `<g class="tm-hl-layer">`, with the `ellipse`'s `rx = ry = floor(icon width/2)+10` and the center at the element center;
   maintenance / disabled are still `fill-opacity: 0.5` semi-transparent boxes.
2. **Layering aligned with native** (shapes → highlights → links → elements): the highlight layer is fixed after all shapes and
   before all links, so links **pass over the highlight circle and connect directly to the host icon**, instead of stopping at the circle edge;
   dragging an element moves the corresponding highlight node along with it.
3. **Refresh interval 60 seconds** (`AUTO_REFRESH_MS = 60000`).
4. **Incremental refresh without flicker**: auto-refresh no longer rebuilds the whole layer, but reuses existing `.tm-el` nodes (keeping the `<img>` object),
   changing only position / state class names / labels, then redraws the highlight layer and links (pure SVG, no image loading),
   consistent with the in-place update of Zabbix's built-in topology map.

### 1.0.18: Remove duplicate status display + customizable discovery depth

**Requirement (real-machine feedback)**: ① two sets of status appeared on host icons — one is the newly aligned native perfect-circle highlight, the other is
a ring of red glow around the icon; ② the discovery depth in "Auto discovery" is hard-coded to a maximum of 4 levels, and should be freely enterable.

**Approach**:

1. **Remove the red glow on icons**: delete `.tm-el.tm-problem .tm-el-icon { filter: drop-shadow(red) }`.
   Status is expressed only by the SVG highlight (perfect circle / maintenance semi-transparent box), consistent with native, with no second set of indicators.
2. **Customizable discovery depth**: the dropdown previously fixed at `1~4 levels` changed to a number box with range `1~10` and default `3`;
   invalid input (empty / 0 / negative / over 10) is automatically clamped, and the input box is written back when out of range.

### 1.0.19: Host status filters disabled triggers by native criteria

**Requirement (real-machine feedback)**: the problem count on host elements is higher than in Zabbix's built-in topology map — the core switch's built-in map shows 1,
while the module shows 2.

**Cause**: the module previously used `problem.get?hostids=...` directly to fetch unresolved problems. After a trigger is disabled (`status=1`),
its residual unresolved problem remains in the problem table (`r_clock=0`), so it is counted extra; native `getSelementsInfo()` only recognizes
triggers that are "enabled + monitored + currently in problem state".

**Approach** (aligned with native):

1. First `trigger.get` with conditions `filter: {state: null}` + `monitored: true` + `only_true: true`
   (value = PROBLEM) + `skipDependent: true`, to obtain triggers that are really firing and monitored;
2. Then use these `triggerid`s in `problem.get` (without `recent`, counting only unresolved), and count per host by trigger;
3. Link triggers likewise only recognize the enabled state (native `CMapHelper` requires `status == TRIGGER_STATUS_ENABLED`);
   residual problems of disabled triggers do not turn links red.

Real-machine check: the core switch's `problem.get?hostids` returns 2, of which `34143` (port down) has `status=1`;
native criteria show 1 trigger; the module's `mode=get` gives that host element `problems=1 / severity=1`, consistent with the built-in topology map.

### 1.0.20: "Rediscover" inside the canvas (in-place update, preserving link metrics)

**Requirement**: after the bandwidth/traffic items are configured for every link in an auto topology map, when a host is added or a host changes ports,
the only option was to run "Auto discovery → Generate map" again to create a new map, and all link metrics had to be reconfigured.

**Approach**: the canvas toolbar adds a **Rediscover** button to the left of "Edit map" (hidden in edit mode, disabled without permission):

1. Use the host elements in the current map as starting points and reuse the "Auto discovery" dialog to rescan (depth/protocol/keywords can be changed);
   the dialog no longer selects hosts, but instead states "starting from the N hosts in the current map";
2. When discovery completes the button becomes **Update map**, and the backend `mode=rediscover` **merges the result into the current map in place**:
   - Devices are matched to old elements by `hostid` (unmanaged by the first line of the label), reusing `selementid` and preserving icon/label;
   - Links are matched first exactly by "element + port", then fall back by device pair (host changed ports), reusing `linkid`
     to preserve the bandwidth / inbound / outbound items in `topomap_link_metrics`;
   - Devices not scanned and manual links are **kept** by default (only add, never remove);
3. Two options in the dialog:
   - **Layout**: `Rearrange all into tree` (default) / `Keep existing coordinates` (new devices are placed below existing elements);
   - **Auto links not rediscovered**: `Keep` (default) / `Remove` (manual lines are always kept);
4. After updating it returns to the canvas and reports "Added X devices, Y links, kept Z link configurations".

### 1.0.21: Link routing (auto orthogonal / manual waypoints)

**Requirement**: can links bend, with the user specifying where to bend.

**Approach**:

1. The "Link" dialog adds **Routing**: `Auto curve` / `Auto orthogonal` / `Manual waypoints`;
2. Auto orthogonal generates horizontal/vertical orthogonal polylines and performs A* pathfinding on a grid, **routing around icons and label text boxes of
   other elements on the canvas** (spacing 18px, each bend has a cost, so overly long detours are not chosen; when no path is found it falls back to "bend once at the midpoint"),
   and link aggregation member lines still get vertical offsets and share one label box as a group;
3. Manual waypoints: click **Pick waypoints on canvas** in the dialog to enter waypoint mode — click empty space to add a waypoint,
   drag a dot to adjust (1:1 follow, converted by the current zoom), double-click a dot to delete, `Esc` to finish;
4. Waypoints are stored in the module-created table `topomap_link_paths` (`linkid` / `sysmapid` / `routing` / `waypoints`),
   and new links claim `linkid` by `new_seq` when saving; when rediscovery reuses a `linkid`, routing is preserved as-is;
5. When element dragging pushes the content outward / the canvas tightens before saving, waypoints pan along with it, and the canvas extent also includes the waypoints.

### 1.0.22: Two routing fixes

1. **Auto orthogonal really avoids obstacles**: changed from "bend once at a fixed midpoint" to A* pathfinding on a candidate grid,
   where obstacles = icons + label text boxes (including status lines) of other elements, avoided by both single lines and link aggregation member lines;
   the elements containing the start / end points are automatically excluded, and it falls back to a midpoint polyline only when no path is found.
2. **Waypoint dragging no longer drifts**: on `mousedown` the waypoint coordinates are snapshotted as numbers, and movement uses "screen delta ÷ current zoom",
   no longer referencing the `waypoints` object that is rewritten every frame (the old implementation added the delta again each frame, so moving the mouse a little made the dot fly far away).

After upgrading, please **Ctrl+F5** to hard-refresh the browser once.

### 1.0.28: Chinese/English support (aligned with the zabbix_cmdb approach)

1. **Language follows the current Zabbix user**: determined in order user language → system default language → en_US, distinguishing only Chinese / English;
   behavior in the Chinese environment is exactly the same as 1.0.27.
2. **New `lib/LanguageManager.php`**: built-in "Chinese original → English" dictionary (about 360 entries), providing `t() / tf()`;
   static page text, backend error messages, and menu name all go through it (previously the menu name/page title used `_()`, and module strings are not in
   the Zabbix dictionary, so they were not translated in the English environment).
3. **Frontend `topomap.js` adds `T() / TF()`**: about 420 visible strings are wrapped in `T()`, and concatenated messages keep the original word order;
   the dictionary is injected by an inline script on the page as `window.TOPOMAP_I18N` (empty table in the Chinese environment, `T()` returns the original text directly,
   and falls back to the original text if not found, so no blank text appears).
4. **All three key pages covered**: the auto-discovery window, map element properties, link properties (including the "Shape" and "Link" dialogs,
   item / trigger selectors); business data such as host names, map names, and device types are not translated.
5. **Default keywords switch with language**: the default keyword for automatic trigger association is `link down` in the English environment,
   and remains "处于关闭状态" in the Chinese environment.

### 1.0.27: Auto-discovery device icons selected by type (aligned with aiops)

1. **Switches / PCs / servers are no longer all Server_(96)**: elements generated by auto discovery choose icons by device type —
   switch `Switch_(128)`, terminal `Workstation_(128)`, server `Server_(96)`,
   router `Router_(128)`, firewall `Firewall_(128)`, printer `Printer_(128)`.
2. **Terminal identification aligned with aiops's `is_distribution`**: if the LLDP peer port is a MAC ⇒ the other side is a terminal NIC
   (PC / printer / AP), `kind` is recorded as `pc`, using `Workstation_(128)`; switch port names are readable names like
   `GigabitEthernet...` / `Gi1/0/11`, keeping `Switch_(128)`.
3. **Old maps automatically upgrade icons**: during "Rediscover" in the canvas, if an old element is still the default
   `Server_(96)` given by auto discovery, it is switched to the icon for the current type; icons manually chosen by the user are not overwritten.
4. Real-machine verification (temporary map, deleted after verification): switch → `Switch_(128)`, server → `Server_(96)`,
   pc → `Workstation_(128)`, router → `Router_(128)`; among the 19 neighbors of 机房办公交换机,
   all terminals are `kind=pc` and the core switch is `kind=switch`.

### 1.0.26: Including unmanaged devices no longer misses devices (aligned with aiops)

1. **All LLDP entries of terminals / APs / printers are kept**: such devices only report `chassisId` (MAC) and
   have an empty `sysName`; the old implementation dropped the whole entry, so only 4 of the 20 LLDP neighbors of "机房办公交换机" were drawn.
   Now they are named by aiops's `name || chassisId` rule, and all 20 neighbors generate nodes.
2. **Multiple terminals under the same port are no longer merged**: the deduplication key was "sysName + local port", and when `sysName` was empty,
   3 / 5 terminals hanging under the same port were squeezed into one; changed to "(sysName, or chassisId if empty) + local port".
3. **The problem of H3C port MAC being used as the interface name**: some ports report the port MAC in `lldpLocPortId`
   (`70:81:85:2C:05:E1`); it is now recognized as an unreadable name and falls back to `IF-MIB ifName`,
   so links show `GigabitEthernet1/0/12` and port triggers can match normally.
4. The frontend node key also falls back to `sysName || chassisId || IP`, and unmanaged nodes become independent nodes by chassis.

Real-machine verification (机房办公交换机 `192.168.40.253`, depth 1): neighbor count **5 → 20**,
3 terminals on `1/0/12` and 5 terminals on `1/0/22` each became their own nodes.

### 1.0.25: Clicking a host in preview mode opens the native host menu

1. In preview mode, single-clicking a host icon (including the host label) opens exactly the same host menu as Zabbix's built-in topology map.
2. Menu data comes directly from Zabbix's built-in `menu.popup` (type=host), then rendered with native `getMenuPopupHost` +
   `$.fn.menuPopup`, so sections, wording, permission checks and disabled states are consistent with the built-in topology map:
   **View** (Dashboards / Problems / Latest data / Graphs / Web monitoring / Asset records),
   **Configuration** (Hosts / Items / Triggers / Graphs / Discovery / Web monitoring),
   **Links** (View Neighbors (LLDP/CDP) injected by third-party modules),
   **Scripts** (Detect operating system / Ping / Traceroute).
3. Menu items jump to the corresponding native pages with a `hostids` filter; "Configuration → Hosts" opens the native `popup.host.edit`
   dialog (the module page does not have the map page's `view.editHost`, so it calls `PopUp` directly).
4. Non-host elements such as images, host groups and triggers do not open the host menu; on error an in-page message is shown without interrupting the canvas.

### 1.0.24: Shapes fully aligned with Zabbix's built-in topology map

1. **Three kinds of shapes**: rectangle / ellipse / **line**. A line's `x,y` is the start point and `width,height` are the absolute coordinates of the end point,
   rendered as SVG `<line>`, exactly consistent with the semantics of native `sysmap_shape` (lines draw no text and fill no background).
2. **Properties window copied from native**: title "Map shape"; shapes are three segmented buttons (rectangle / ellipse / line);
   text; font (13 fonts including Helvetica, indices consistent with native), font size, color;
   horizontal / vertical alignment (center / left / right, center / top / bottom); background color;
   border type / width (px) / color; coordinates X/Y; size width (px)/height (px); buttons Apply / Remove / Close.
   When switching to "line", the text and background rows are automatically collapsed, labels change to native's line / point / X1, Y1 / X2, Y2,
   and coordinates are converted between "bounding box ↔ two endpoints" by native rules.
3. **Text and border details aligned**: font families rendered per native `SVGMap.FONTS` (font=9 displays Helvetica, actually drawn
   Trebuchet MS), padding 5px, text split into multiple lines and clipped inside the shape; dotted / dashed dash arrays scale with line width
   (`1,2` / `4,4`, dotted uses round caps when line width > 2), and line width is 0 when border type is 0.
4. **Resize by mouse drag**: single-click a shape to select it, showing **8 7×7 circular handles** consistent with native
   (four corners + four edge midpoints, white fill, 1px dark blue stroke; the dark theme automatically switches to dark fill with light stroke); drag a handle to change
   `x/y/width/height`, rectangle/ellipse minimum 1px; when a line is selected there are **2 endpoint dots**, and dragging an endpoint changes the start / end point.
   Double-click a shape to open the properties window, click empty space to deselect.
5. Default values for new shapes are consistent with native: start at `10,10`, `50×50`, black solid 2px, Helvetica 11 black text.

After upgrading, please **Ctrl+F5** to hard-refresh the browser once.

### 1.0.23: Links in the auto-discovery tree layout

1. **Anchor moved down**: links between upper and lower levels start from **6px below** the parent node's "icon + name + OK / problem" text block
   (no longer the bottom edge of the icon), and land 6px above the top edge of the child node's icon; when the label text has multiple lines (multi-line host names,
   multiple problems) the anchor yields downward accordingly. Links within the same level / left-right neighbors still connect directly at element centers.
2. **A single subordinate uses a straight line, multiple use auto orthogonal**: when a parent node has only 1 "device" below, connect with a straight line
   (the two member ports of a link aggregation also remain a straight line, not bending just because there are multiple member ports);
   with multiple devices, first go vertically to a shared trunk 30px below the parent anchor, split horizontally to directly above each child node, then drop vertically.
3. **Horizontal trunk does not hug icons**: the tree layout level spacing increased from 180 to **300**, so the trunk falls below the parent text block
   and is still 100px or more above the child icons — this vertical space is used to place the child nodes' bandwidth / traffic boxes vertically.
4. **Bandwidth / traffic boxes centered on the child node's vertical line**: for tree orthogonal links, the metric box no longer falls at the routing midpoint (which would sit on the horizontal trunk
   and make it unclear which host it belongs to), but is horizontally centered directly above the child node and vertically at the **midpoint** of the vertical
   segment "trunk bend → child node icon"; when child nodes move down and level spacing grows, lines get longer and the box automatically stays centered (consistent with Zabbix's built-in topology map).
5. **Metric box border follows the link color**: normal links have a green box (`#00CC00`), and when the link's trigger fires / the port goes down it turns into a red box
   (`#DD0000`), using the same color value as the line itself, so they match at a glance.
6. **Link aggregation parallel lines**: multiple member ports of the same element pair are **laid out side by side with the same offset at both ends** (spacing 12px),
   drawn as parallel vertical lines + parallel horizontal segments, no longer converging to a point at both ends; each member's trunk is offset from the others by one line spacing,
   so they do not overlap into one line. The whole bundle leans toward the child node side, avoiding crossing the vertical line of "the host directly below".
7. **Auto-discovery links default to "Auto orthogonal"**: links with port names written at both ends during discovery use tree routing directly,
   without changing each one in the "Link" dialog; manually created links still default to "Auto curve".
8. "Auto curve" is also persisted (`topomap_link_paths`): after switching to auto curve it is no longer rendered back to auto orthogonal by the default value.
9. Member ports still **each match triggers by their own port**; only that one member port turns red when it enters problem state, not the whole group.

> To see the new spacing in an existing auto-discovery map, click "Rediscover" on the canvas → choose "Rearrange all into tree" for layout
> (device / link / port / trigger configurations are all preserved).

After upgrading, please **Ctrl+F5** to hard-refresh the browser once.

---

Author: Mars · Version 1.0.28

