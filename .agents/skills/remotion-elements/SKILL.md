---
name: remotion-elements
description: "Reference catalog and implementation guide for Remotion Elements (https://www.remotion.dev/elements). Drop-in, customizable, HTML-first video building blocks for Remotion compositions, animations, charts, overlays, captions, and backgrounds."
---

# Remotion Elements - Video Building Blocks

Reference guide for integrating drop-in, customizable building blocks from [Remotion Elements](https://www.remotion.dev/elements) into React / Remotion video compositions.

---

## 1. Core Principles

- **Drop-in & Remixable:** Every Element is built with standard React, HTML/SVG/Canvas, CSS, and Remotion hooks (`useCurrentFrame`, `useVideoConfig`, `interpolate`, `spring`).
- **Sequencing:** Compose elements seamlessly using Remotion's `<Sequence>` component with dedicated `from` and `durationInFrames` props.
- **Parametrization:** Elements expose props for colors, typography, timing curves, labels, and datasets to allow instant branding.

---

## 2. Element Catalog by Category

### A. Backgrounds (`/elements/backgrounds`)
* **Liquid Contours:** Flowing two-color procedural background created with animated SVG contour bands.
* **Notebook Paper:** Clean white paper background with subtle blue gridlines, margins, and paper textures.
* **Paper Texture:** Organic white/creme paper texture with slowly evolving posterized seed.
* **Rotating Starburst:** Retro/modern radial sunburst/starburst rotating smoothly behind foreground elements.

### B. Captions & Subtitles (`/elements/captions`)
* **Moving Pill Captions:** Synchronized subtitle words where an active background pill slides smoothly to the current spoken word.
* **Popping Word Captions:** TikTok/Reels style popping kinetic typography that scales and highlights each spoken word.
* **Word Highlight Captions:** High-visibility captioning with inline highlight color shifts.

### C. Commerce & Marketing (`/elements/commerce`)
* **Product Discount Callout:** Animated product badge with pricing sticker and hinged bouncy discount tag.
* **Product Offer:** E-commerce product card featuring title, product image, discount percentage, and CTA badge.

### D. Data Visualizations & Charts (`/elements/data`)
* **Horizontal Bar Chart:** Animated horizontal comparison bars with direct data labels and percentage interpolations.
* **Vertical Bar Chart:** Staggered vertical bar charts with entrance springs and value labels.
* **Line Chart:** Smooth SVG line chart with animated stroke dashoffset drawing and glowing endpoint markers.
* **Pie Chart:** Radial SVG pie/donut slices animated with strokeDasharray and legend indicators.
* **Number Counter:** Smooth numeric ticker counting up from start to target value using Remotion interpolation.

### E. Maps & Geolocation (`/elements/maps`)
* **A-to-B Map Flyover:** Animated map camera panning from Origin to Destination coordinates with curved flight path and pin markers.

### F. Overlays & Lower Thirds (`/elements/overlays`)
* **Name Lower Third:** Sleek broadcast-quality lower third for speaker name, role, and organization.
* **Location Lower Third:** Location identifier with pin icon, city/venue title, and subtitle metadata.

### G. Storytelling & Text Callouts (`/elements/storytelling` & `/elements/text`)
* **News Article Highlight:** 3D tilted document / article viewport with camera glide and glowing marker highlighter over text lines.
* **Text Marker:** Animated hand-drawn highlighter stroke over specific words.
* **Circle Marker:** Animated rough circle / oval drawn around key phrases.
* **Crossed Off:** Animated hand-drawn 'X' or scratch effect to dismiss text.
* **Strike Through:** Animated horizontal line crossing out old prices or outdated words.
* **Spinning Text Wheel:** 3D vertical roulette / text wheel that spins and settles on a winning phrase.

### H. YouTube & Social Outros (`/elements/youtube`)
* **YouTube End Card:** Grid card template reserving standard 16:9 boxes for next video recommendations and circular avatar subscribe button.

---

## 3. Composition Patterns & Best Practices

### Embedding in Sequences
```tsx
import { Sequence } from 'remotion';
import { LiquidContoursBackground } from './elements/LiquidContours';
import { NameLowerThird } from './elements/NameLowerThird';

export const MainComposition = () => {
  return (
    <>
      {/* Background layer spanning full video */}
      <Sequence from={0} durationInFrames={300}>
        <LiquidContoursBackground colorPrimary="#1e293b" colorSecondary="#0f172a" />
      </Sequence>

      {/* Speaker intro overlay from 1s to 5s (30fps) */}
      <Sequence from={30} durationInFrames={120}>
        <NameLowerThird 
          title="Dr. Alex Rivera" 
          subtitle="Head of Autonomous Systems" 
          accentColor="#2563eb"
        />
      </Sequence>
    </>
  );
};
```

### Spring Animations & Timing Curves
Always use `spring()` with `fps` from `useVideoConfig()` for natural physics:
```tsx
import { useCurrentFrame, useVideoConfig, spring, interpolate } from 'remotion';

const frame = useCurrentFrame();
const { fps } = useVideoConfig();

const entrance = spring({
  frame,
  fps,
  config: { damping: 12, stiffness: 100, mass: 0.5 },
});

const translateY = interpolate(entrance, [0, 1], [60, 0]);
const opacity = interpolate(entrance, [0, 1], [0, 1]);
```
