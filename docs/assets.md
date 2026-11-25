# Image assets: what each file is and where it came from

Every image the running application serves lives in
`digital-leap/public/assets/img/`. Nothing is loaded from a CDN, a font
service or any other host: the CSP is `script-src 'self'`, and the service
worker precaches the shell.

## One scene is a generated illustration, one is the team's own photograph

`campus-hero.jpg` and its two derived sizes are **not photographs**. They were
produced with an image-generation model from written prompts, then cropped and
resized locally. They show no real student, no real building and no real event
at Limkokwing University, so the landing hero carries the caption
**"Illustrative scene"**; the caption is part of the markup, not an optional
nicety.

The sign-in surface is different: `campus-graduation.jpg` and its 960 px
candidate are the **graduation photograph the team supplied**. The only work
done on them was local enlargement for sharpness on high-density screens
(contrast 1.05, saturation 1.06, unsharp mask, 2× upscale). No content was
added, removed or restyled, so the sign-in page and the phone strip credit it
as **"Limkokwing graduation"** rather than calling it illustrative.

## Provenance table

| File | Pixels | Bytes | Used by | Provenance |
| --- | --- | --- | --- | --- |
| `campus-hero.jpg` | 1536 × 1024 | 244,752 | Landing `figure.home-hero-media` (`src`, `srcset` 1536w) | **Generated illustration.** Resized with PowerShell `System.Drawing`, HighQualityBicubic. |
| `campus-hero-1200.jpg` | 1200 × 800 | 112,777 | Same `srcset`, 1200w candidate | Downscaled from `campus-hero.jpg`, JPEG quality 64 |
| `campus-hero-768.jpg` | 768 × 512 | 72,066 | Same `srcset`, 768w candidate — what a phone downloads | Downscaled from `campus-hero.jpg` |
| `campus-graduation.jpg` | 1904 × 680 | 229,138 | `body.auth-shell` backdrop (full width) | **Team-supplied photograph.** Locally upscaled 2× and sharpened; no content changed |
| `campus-graduation-960.jpg` | 960 × 343 | 91,675 | `srcset` 960w candidate — the ≤560 px `.auth-strip` and phone download | Downscaled from `campus-graduation.jpg`, under the 120 KB phone budget |
| `leap-badge.png` | 1330 × 1060 | 430,346 | Brand only; kept for the report and previews | Trimmed from `docs/brand-source/leap-badge.jpeg` (supplied badge) |
| `leap-globe.png` | 456 × 456 | 474,886 | Not referenced by the running app | Circular crop of `docs/brand-source/limkokwing-globe.png`, white removed |
| `leap-mark.png` | 1158 × 625 | 154,292 | Not referenced by the running app (social/splash plate) | Badge plate cut from the supplied badge |
| `leap-wordmark-ink.png` | 1013 × 566 | 78,374 | Header, landing header, sign-in tile | Transparent "Leap" + arc cut from the supplied badge, charcoal |
| `leap-wordmark-ivory.png` | 1013 × 566 | 79,673 | Report/preview use on dark surfaces | Same cut-out, ivory |
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
photograph is likewise the team's own file, resampled rather than redrawn.

## Rules that follow from the table

1. **The wordmark is never placed on a photograph.** Use the ink variant on
   light surfaces and the ivory or gold variant on dark ones.
2. **Do not stretch the wordmark.** Its ratio is 1.79 : 1; height 28–40 px in
   headers.
3. **Do not upscale the globe.** It is a 234 px crop; above roughly 300 px wide
   use the CSS globe instead.
4. **Any picture that is not a photograph says so.** The landing hero uses the
   caption "Illustrative scene"; new images that are generated must carry the
   same caption. The graduation photograph is captioned "Limkokwing graduation"
   and must never be re-captioned as illustrative. Team-supplied photographs are
   enhanced in place — sharpened, never swapped for a generated stand-in.
5. **Keep the size budget.** A phone receives `campus-hero-768.jpg` at 72 KB and
   `campus-graduation-960.jpg` at 92 KB, both under the 120 KB target. If a scene
   is re-exported, re-check the small candidate and the `srcset` descriptors
   together.
