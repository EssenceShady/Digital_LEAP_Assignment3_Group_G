# Image assets: what each file is and where it came from

Every image the running application serves lives in
`digital-leap/public/assets/img/`. Nothing is loaded from a CDN, a font
service or any other host: the CSP is `script-src 'self'`, and the service
worker precaches the shell.

## Both scenes are the team's own photographs

The landing stage and the sign-in surface carry photographs the owner supplied,
not generated scenes. The landing picture is the **flag-waving crowd outside the
university building**; the sign-in picture is the **graduation portrait** that
was already there. The only work done on either is local resampling for size
and sharpness. No content was added, removed, extended or restyled, so neither
is captioned "illustrative".

The earlier generated `campus-hero*.jpg` scene and its "Illustrative scene"
caption were retired on 26 November 2026 at the owner's request; the three files
were deleted, and no template or stylesheet references them.

The flag globe is likewise the owner's artwork: a circular crop of the supplied
poster, used as the landing stage seal and as the turning coin on the boot
splash.

## Provenance table

| File | Pixels | Bytes | Used by | Provenance |
| --- | --- | --- | --- | --- |
| `campus-flags.jpg` | 1320 × 741 | 261,246 | Landing `.home-stage-photo` (`src`, `srcset` 1320w) | **Team-supplied photograph.** Resized with PowerShell `System.Drawing`, HighQualityBicubic; no content changed |
| `campus-flags-960.jpg` | 960 × 539 | 109,329 | `<source media="(max-width: 768px)">` — what a phone downloads | Re-encoded from `campus-flags.jpg` at JPEG quality 76 to stay under the 120 KB phone budget |
| `globe-flag-sphere.png` | 340 × 340 | 192,590 | Boot splash coin, landing stage seal (PNG fallback) | **Team-supplied artwork.** Circular crop of the supplied globe poster, soft edge and warm rim retained |
| `globe-flag-sphere.webp` | 340 × 340 | 22,002 | Same two places, WebP candidate | Lossy WebP of the PNG; served first through `<picture>` |
| `campus-graduation.jpg` | 1904 × 680 | 229,138 | `body.auth-shell` backdrop (full width) | **Team-supplied photograph.** Locally upscaled 2× and sharpened; no content changed |
| `campus-graduation-960.jpg` | 960 × 343 | 91,675 | `srcset` 960w candidate — the ≤560 px `.auth-strip` and phone download | Downscaled from `campus-graduation.jpg`, under the 120 KB phone budget |
| `leap-badge.png` | 1330 × 1060 | 430,346 | Brand only; kept for the report and previews | Trimmed from `docs/brand-source/leap-badge.jpeg` (supplied badge) |
| `leap-globe.png` | 456 × 456 | 474,886 | Not referenced by the running app | Circular crop of `docs/brand-source/limkokwing-globe.png`, white removed |
| `leap-mark.png` | 1158 × 625 | 154,292 | Not referenced by the running app (social/splash plate) | Badge plate cut from the supplied badge |
| `leap-wordmark-ink.png` | 1013 × 566 | 78,374 | Header, landing header, sign-in tile | Transparent "Leap" + arc cut from the supplied badge, charcoal |
| `leap-wordmark-ivory.png` | 1013 × 566 | 79,673 | Boot splash wordmark; report/preview use on dark surfaces | Same cut-out, ivory |
| `leap-wordmark-gold.png` | 1013 × 566 | 79,825 | Report/preview accent use | Same cut-out, gold |
| `diva-logo.png` | 840 × 823 | 209,458 | Preview/screenshots only | DIVA brain mark, part of the supplied badge |
| `diva-mark.png` | 256 × 256 | 42,076 | DIVA page header | 96 px derivative of the DIVA mark, enlarged for retina |
| `diva-mark-sm.png` | 96 × 96 | 11,159 | Corner launcher and DIVA header | Compact transparent draft of the same mark |
| `icon-192.png` | 192 × 192 | 8,909 | Web app manifest, favicon, apple-touch-icon | White tile with the ink LEAP wordmark, matching the header `.logo-tile` |
| `icon-512.png` | 512 × 512 | 36,774 | Web app manifest, maskable icon | Same tile at 512 px |
| `limkokwing-logo.png` | 130 × 75 | 16,201 | Footer (`site-foot`) and the LUCT lockup | University logo, used to identify the institution |
| `limkokwing-graduation.jpg` | 952 × 340 | 112,666 | Not referenced by the running app | The supplied graduation photograph at its original export size; superseded by `campus-graduation.jpg` |

The two originals in `docs/brand-source/` are the supplied artwork; everything
derived from them is recolouring and cut-out work done locally, not redrawing.
`docs/brand-preview/` holds preview exports of the same family. The graduation
and campus photographs are likewise the team's own files, resampled rather
than redrawn.

## Rules that follow from the table

1. **The wordmark is never placed on a photograph.** Use the ink variant on
   light surfaces and the ivory or gold variant on dark ones.
2. **Do not stretch the wordmark.** Its ratio is 1.79 : 1; height 28–40 px in
   headers.
3. **Do not upscale the globe.** The sphere is a 340 px crop shown at most
   ~210 CSS px (74 % of a 280 px orb), so it stays sharp at 1× and 1.5×
   density; above that the browser is enlarging it. It is never stretched
   wider than square.
4. **Any picture that is not a photograph says so.** Both landing and sign-in
   photographs are real and are captioned for place ("Students at Limkokwing
   University, Sierra Leone", "Limkokwing graduation"). If a generated image is
   ever added it must carry an "Illustrative scene" caption instead. Team-
   supplied photographs are enhanced in place — resampled, sharpened, never
   swapped for a generated stand-in.
5. **Keep the size budget.** A phone receives `campus-flags-960.jpg` at 109 KB
   and `campus-graduation-960.jpg` at 92 KB, both under the 120 KB target.
   Desktop receives the 1320 px campus picture at 261 KB and the 340 px globe
   as 22 KB of WebP. If a scene is re-exported, re-check the small candidate,
   the byte count and the `srcset` descriptors together.
