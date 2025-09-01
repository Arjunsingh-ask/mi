git branch -M main
git remote add origin https://github.com/Arjunsingh-ask/mi.git  # (only if not already set)
git push -u origin main

# AI SEO Agent

**AI SEO Agent** is a powerful, lightweight WordPress SEO plugin with full Yoast-like features: real-time content optimization, schema, XML sitemaps, FAQ builder, and a complete settings dashboard.

---

## 🎉 Features

- **OOP Modular Design** (easy to extend, dev-friendly)
- **Meta Box** for per-post SEO (focus keyword, keyword density, snippet preview, FAQ builder)
- **Bulk Content Optimizer** (optimize multiple posts at once)
- **Schema Markup**: Article, Product, FAQ, GEO, AI Overview (toggle on/off)
- **URL & Slug Optimization**: Smart suggestions, one-click update
- **XML Sitemap**: Auto-updating, search engine pings
- **Dashboard Widget**: SEO health, one-click actions
- **Settings Panel**: General, Content, Schema, Sitemap, Advanced (robots.txt, canonical, etc)
- **Fast & Secure**: No heavy JS, all input sanitized, nonces everywhere

---

## 🚀 Installation

1. Download the latest release `.zip` of this plugin.
2. In your WordPress admin, go to **Plugins > Add New**.
3. Click **Upload Plugin** and select the `.zip`.
4. Activate **AI SEO Agent**.
5. Go to **SEO Agent** in the admin menu to configure.

---

## 🛠️ Setup & Usage

- Configure your global SEO, schema, and sitemap settings under **SEO Agent**.
- For each post/page, use the **SEO Agent Optimization** meta box to set focus keywords, review snippet preview, and add FAQs.
- Use the **Bulk Content** submenu for batch optimization.
- Dashboard widget lets you check SEO health and run one-click actions.

---

## ❓ FAQ

**Q: Is it compatible with Gutenberg and Classic Editor?**  
A: Yes, fully compatible.

**Q: How do I add FAQ schema?**  
A: Use the FAQ Builder in the post meta box; Q&A pairs are auto-injected as JSON-LD.

**Q: Can I extend it?**  
A: Yes! The codebase is modular and hooks/filters are provided for developers.

**Q: Does it slow down my site?**  
A: No, frontend JavaScript is minimal. All heavy tasks run only in admin.

---

## 🧑‍💻 Developer Notes

- All classes reside in `/includes/`.
- Hooks and filters: `ai_seo_agent_meta_box_post_types`, etc.
- Safe, secure, and modular for future updates.

---

© 2025 Arjunsingh-ask. GPL2.

