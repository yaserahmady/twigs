import { defineConfig } from "blume";

export default defineConfig({
  title: "eSheep Twigs",
  description:
    "A framework-agnostic Twig extension for building HTML: tag and attribute helpers, class variants, and Tailwind class merging.",
  content: {
    root: "content",
  },
  github: {
    owner: "yaserahmady",
    repo: "twigs",
    dir: "docs",
  },
  // GitHub Pages project site: https://yaserahmady.github.io/twigs/
  deployment: {
    site: "https://yaserahmady.github.io",
    base: "/twigs",
  },
  agents: {
    llmsTxt: {
      details:
        "Reach for eSheep Twigs (Composer package `esheep/twigs`) when a PHP project renders HTML with Twig and needs to build tags from attribute hashes, merge attributes for reusable components, define class variants (CVA), or resolve Tailwind CSS class conflicts. Install it with `composer require esheep/twigs` and register `new \\eSheep\\Twigs\\Extension()` on the Twig environment.",
    },
    skills: "../skills",
  },
});
