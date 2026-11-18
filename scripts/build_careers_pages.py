#!/usr/bin/env python3
"""Generate careers.coralpetal.ai pages (2026-11-18 redesign, Rohit).
   python3 scripts/build_careers_pages.py
Shares the main site's stylesheet (copied to careers/css/main.css) plus careers/css/careers.css."""
import shutil
from pathlib import Path
ROOT = Path(__file__).resolve().parent.parent
C = ROOT / "careers"
shutil.copyfile(ROOT / "site/css/styles.css", C / "css/main.css")
V = "20261118w"

SOC = '''<div class="social"><a class="s-li" href="https://www.linkedin.com/company/coralpetalai" target="_blank" rel="noopener" aria-label="Coral Petal AI on LinkedIn"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M20.45 20.45h-3.55v-5.57c0-1.33-.02-3.04-1.85-3.04-1.86 0-2.14 1.45-2.14 2.94v5.67H9.36V9h3.41v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28zM5.34 7.43a2.06 2.06 0 1 1 0-4.12 2.06 2.06 0 0 1 0 4.12zM7.12 20.45H3.56V9h3.56v11.45zM22.22 0H1.77C.79 0 0 .77 0 1.73v20.54C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.73V1.73C24 .77 23.2 0 22.22 0z"/></svg></a><a class="s-ig" href="https://www.instagram.com/coralpetalai/" target="_blank" rel="noopener" aria-label="Coral Petal AI on Instagram"><svg viewBox="0 0 24 24" aria-hidden="true"><defs><linearGradient id="cp-ig" x1="0" y1="1" x2="1" y2="0"><stop offset="0" stop-color="#FEDA75"/><stop offset=".3" stop-color="#FA7E1E"/><stop offset=".6" stop-color="#D62976"/><stop offset=".85" stop-color="#962FBF"/><stop offset="1" stop-color="#4F5BD5"/></linearGradient></defs><rect x="2.5" y="2.5" width="19" height="19" rx="5.5" fill="none" stroke="url(#cp-ig)" stroke-width="2"/><circle cx="12" cy="12" r="4.3" fill="none" stroke="url(#cp-ig)" stroke-width="2"/><circle cx="17.6" cy="6.4" r="1.3" fill="#D62976"/></svg></a><a class="s-x" href="https://x.com/CoralPetalAI" target="_blank" rel="noopener" aria-label="Coral Petal AI on X"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M18.9 2H22l-7.4 8.5L23.3 22h-6.8l-5.3-6.9L5 22H1.9l7.9-9.1L1.6 2h7l4.8 6.3L18.9 2zm-1.2 18h1.9L7.4 3.9H5.4L17.7 20z"/></svg></a></div>'''

def page(title, desc, canon, body, cur):
    oc = ' aria-current="page"' if cur == "openings" else ""
    return f'''<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{title}</title>
  <meta name="description" content="{desc}">
  <link rel="canonical" href="https://careers.coralpetal.ai{canon}">
  <link rel="icon" href="/img/favicon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="/img/apple-touch-icon.png">
  <meta property="og:title" content="{title}">
  <meta property="og:description" content="{desc}">
  <meta property="og:image" content="https://careers.coralpetal.ai/img/og-careers.png">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="theme-color" content="#FBFBFB">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Newsreader:opsz,wght@6..72,400;6..72,500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/css/main.css?v={V}">
  <link rel="stylesheet" href="/css/careers.css?v={V}">
</head>
<body>
  <a class="skip" href="#main">Skip to content</a>
  <header class="site-header">
    <div class="wrap nav">
      <a class="brand" href="https://coralpetal.ai/" aria-label="Coral Petal AI, home">
        <img class="mark" src="/img/logo.svg" alt="" width="36" height="36">
        <span>Coral <span class="accent">Petal AI</span><sup class="tm">™</sup></span>
      </a>
      <nav aria-label="Primary">
        <ul id="primary-nav">
          <li><a class="pill" href="/openings/"{oc}>Openings</a></li>
        </ul>
      </nav>
    </div>
  </header>

  <main id="main">
{body}
  </main>

  <footer class="site-footer">
    <div class="wrap">
      <div class="foot-bottom" style="margin-top:0;border-top:0;padding-top:0">
        <span>© <span id="year">2026</span> Coral Petal AI™ and the Petal Network logo are trademarks of The Coral Petal Company.</span>
        <span class="foot-right">{SOC}<a href="mailto:careers@coralpetal.ai">careers@coralpetal.ai</a></span>
      </div>
    </div>
  </footer>
  <script>document.getElementById('year').textContent = new Date().getFullYear();</script>
</body>
</html>
'''

def ico(cls, path):
    return f'<span class="ico-soft {cls}"><svg viewBox="0 0 24 24">{path}</svg></span>'

I = {
 "agent": '<rect x="3" y="4" width="18" height="16" rx="3"/><path d="M9 10l-2 2 2 2M15 10l2 2-2 2"/>',
 "data": '<path d="M4 19h16M7 16v-5M12 16V8M17 16v-8"/>',
 "cloud": '<path d="M7 18h10a4 4 0 0 0 .5-8A6 6 0 0 0 6 9.5 4.3 4.3 0 0 0 7 18z"/>',
 "teach": '<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2z"/><path d="M4 19V5"/>',
 "video": '<rect x="3" y="6" width="13" height="12" rx="2"/><path d="M16 10l5-3v10l-5-3"/>',
 "people": '<circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6M14 20c0-2.5 1.5-4.5 3-4.5s4 1.5 4 4.5"/>',
 "globe": '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
 "spark": '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M6 18l2.5-2.5M15.5 8.5 18 6"/>',
 "check": '<circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/>',
 "pen": '<path d="M4 20l4-1 11-11-3-3L5 16z"/>',
 "mail": '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
}

# ---------- Careers home ----------
home = f'''    <section class="page-hero">
      <div class="wrap ph-grid">
        <div>
          <p class="eyebrow">Careers</p>
          <h1>Help enterprises put Agentic AI to work.</h1>
          <p class="lede">Join a small, remote team that builds, teaches and ships Agentic AI, and works alongside AI agents every day.</p>
          <div class="cta-row"><a class="btn" href="/openings/">See openings</a><a class="btn btn-ghost" href="https://coralpetal.ai/vision/">Our core vision</a></div>
        </div>
        <figure class="viz coral-card cr-team" aria-label="Illustration: a remote team of people and AI agents working together">
          <div class="viz-h"><span>Our team, at work</span><span class="pill-s p-green"><i class="live-dot"></i>Hiring</span></div>
          <div class="cr-orbit" aria-hidden="true">
            <div class="cr-core"><img src="/img/logo.svg" alt=""></div>
            <span class="cr-node n1">{ico("is-blue", I["agent"])}<b>Engineering</b></span>
            <span class="cr-node n2">{ico("is-violet", I["data"])}<b>Data science</b></span>
            <span class="cr-node n3">{ico("is-green", I["teach"])}<b>Academy</b></span>
            <span class="cr-node n4">{ico("is-coral", I["video"])}<b>Content</b></span>
            <span class="cr-node n5">{ico("is-amber", I["cloud"])}<b>Cloud</b></span>
          </div>
          <div class="cr-foot"><span>{ico("is-teal", I["globe"])}</span><div><b>Fully remote</b><small>People + AI agents, one workflow</small></div></div>
        </figure>
      </div>
    </section>

    <section class="section">
      <div class="wrap">
        <div class="section-head">
          <p class="eyebrow chip">Who we hire</p>
          <h2>Roles across consulting, the Academy and content.</h2>
        </div>
        <div class="grid g3 pillars">
          <article class="coral-card pillar"><div class="pl-top">{ico("is-blue", I["agent"])}</div><h3>Agentic AI engineering</h3><p>Design and build agents, retrieval and tool integrations for enterprise clients.</p></article>
          <article class="coral-card pillar"><div class="pl-top">{ico("is-violet", I["data"])}</div><h3>Data science &amp; ML</h3><p>Models, evaluation and the data work behind reliable agents.</p></article>
          <article class="coral-card pillar"><div class="pl-top">{ico("is-amber", I["cloud"])}</div><h3>Cloud &amp; platform</h3><p>Deploy, secure and run agents on Azure, AWS and GCP.</p></article>
          <article class="coral-card pillar"><div class="pl-top">{ico("is-green", I["teach"])}</div><h3>Instructors &amp; SMEs</h3><p>Teach Academy courses and live sessions on what we build.</p></article>
          <article class="coral-card pillar"><div class="pl-top">{ico("is-coral", I["video"])}</div><h3>Content &amp; design</h3><p>Video, slides and graphics for courses, clients and social.</p></article>
          <article class="coral-card pillar"><div class="pl-top">{ico("is-teal", I["people"])}</div><h3>Delivery &amp; client success</h3><p>Keep projects on track and clients getting value.</p></article>
        </div>
      </div>
    </section>

    <section class="section steps-band">
      <div class="wrap">
        <div class="section-head">
          <p class="eyebrow chip">How we work</p>
          <h2>What working here is like.</h2>
        </div>
        <div class="grid g4 pillars">
          <article class="coral-card pillar"><div class="pl-top">{ico("is-teal", I["globe"])}</div><h3>Remote first</h3><p>Work from anywhere, async by default.</p></article>
          <article class="coral-card pillar"><div class="pl-top">{ico("is-coral", I["spark"])}</div><h3>With AI agents</h3><p>Agents are part of everyone's daily workflow.</p></article>
          <article class="coral-card pillar"><div class="pl-top">{ico("is-green", I["teach"])}</div><h3>Always learning</h3><p>Full access to the Academy, and time to use it.</p></article>
          <article class="coral-card pillar"><div class="pl-top">{ico("is-blue", I["check"])}</div><h3>Real impact</h3><p>Small team, so your work ships and matters.</p></article>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="wrap">
        <div class="cta-band">
          <p class="eyebrow plain">Openings</p>
          <h2>Find your role.</h2>
          <p>See the roles we are hiring for now, or send us your CV for the future.</p>
          <div class="cta-row"><a class="btn" href="/openings/">See openings</a></div>
        </div>
      </div>
    </section>
'''

# ---------- Openings ----------
openings = f'''    <section class="page-hero cr-hero-s">
      <div class="wrap">
        <p class="eyebrow">Openings</p>
        <h1>Open roles at Coral Petal AI.</h1>
        <p class="lede">All roles are fully remote. Click a role to see the full description.</p>
      </div>
    </section>

    <section class="section" style="padding-top:24px">
      <div class="wrap">
        <div class="section-head"><p class="eyebrow chip">Open roles</p><h2>Hiring now</h2></div>
        <div class="cr-roles">
          <a class="coral-card cr-role" href="/video-editor/">
            {ico("is-coral", I["video"])}
            <div class="cr-role-main">
              <h3>Video Editor</h3>
              <p>Enterprise-grade video for courses, walkthroughs and client work, produced alongside AI agents.</p>
              <ul class="pl-chips"><li>Content</li><li>Fully remote</li><li>Figma</li><li>AI agents</li></ul>
            </div>
            <span class="cr-go">View role <span aria-hidden="true">→</span></span>
          </a>
        </div>

        <div class="coral-card cr-open">
          {ico("is-blue", I["mail"])}
          <div><h3>Don't see your role?</h3><p>Send your CV and a short note about the work you do best. We keep it on file and get in touch when something fits.</p></div>
          <a class="btn" href="mailto:careers@coralpetal.ai">careers@coralpetal.ai</a>
        </div>
      </div>
    </section>
'''

# ---------- Video Editor JD ----------
def items(rows):
    return "\n".join(f'            <article class="coral-card cr-item">{ico(c, I[k])}<div><b>{t}</b><p>{d}</p></div></article>' for c, k, t, d in rows)

do = [
 ("is-coral","video","Edit to a brand standard","Pacing, cuts, color, audio, captions, lower thirds and motion graphics."),
 ("is-blue","teach","Build learning content","Turn screen recordings, talking heads and slides into clear lessons."),
 ("is-violet","spark","Work with AI agents","Transcripts, rough cuts, B-roll, subtitles and translations, reviewed by you."),
 ("is-green","check","Keep a human sign-off","Brief agents, check their output and approve everything that ships."),
 ("is-amber","pen","Create reusable templates","Presets and checklists so every video meets the same bar."),
 ("is-teal","globe","Deliver everywhere","Versions and formats for web, LinkedIn, YouTube and learning platforms."),
]
bring = [
 "A strong editing portfolio, ideally corporate, training or technical content",
 "Expert in Premiere Pro, DaVinci Resolve or Final Cut Pro; After Effects for motion",
 "Strong Figma: slides, thumbnails, title cards and on-screen graphics",
 "Hands-on with AI video and audio tools, and with delegating to AI agents",
 "A sharp eye for detail, accessibility and a consistent brand",
 "Clear writing and comfort working remotely and asynchronously",
]
jd = f'''    <section class="page-hero cr-hero-s">
      <div class="wrap">
        <p class="crumbs"><a href="/">Careers</a> <span>/</span> <a href="/openings/">Openings</a> <span>/</span> Video Editor</p>
        <div class="cr-jd-head">
          <div>
            <p class="eyebrow">Content · Fully remote</p>
            <h1>Video Editor</h1>
            <p class="lede">Turn raw recordings into polished, enterprise-grade video for our courses, walkthroughs, client work and YouTube, with AI agents in your everyday workflow.</p>
          </div>
          <a class="btn" href="mailto:careers@coralpetal.ai?subject=Video%20Editor">Apply now</a>
        </div>
        <div class="cr-facts">
          <div class="coral-card">{ico("is-coral", I["people"])}<div><small>Team</small><b>Content</b></div></div>
          <div class="coral-card">{ico("is-teal", I["globe"])}<div><small>Location</small><b>Fully remote</b></div></div>
          <div class="coral-card">{ico("is-violet", I["pen"])}<div><small>Must have</small><b>Figma, AI agents</b></div></div>
          <div class="coral-card">{ico("is-blue", I["mail"])}<div><small>Apply</small><b>careers@coralpetal.ai</b></div></div>
        </div>
      </div>
    </section>

    <section class="section" style="padding-top:24px">
      <div class="wrap">
        <div class="section-head"><p class="eyebrow chip">The role</p><h2>What you'll do</h2></div>
        <div class="grid g3 cr-items">
{items(do)}
        </div>
      </div>
    </section>

    <section class="section steps-band">
      <div class="wrap cr-two">
        <div>
          <div class="section-head"><p class="eyebrow chip">You</p><h2>What you bring</h2></div>
          <ul class="cr-checks">
{chr(10).join(f'            <li>{b}</li>' for b in bring)}
          </ul>
          <p class="cr-nice"><b>Nice to have:</b> <span class="pill-s p-grey">Data science, AI or cloud topics</span> <span class="pill-s p-grey">YouTube optimization</span></p>
        </div>
        <aside class="coral-card cr-apply">
          {ico("is-coral", I["mail"])}
          <h3>How to apply</h3>
          <ol>
            <li>Your CV</li>
            <li>A portfolio link</li>
            <li>A short note on how you've worked with AI agents in your editing</li>
          </ol>
          <a class="btn" href="mailto:careers@coralpetal.ai?subject=Video%20Editor">Apply by email</a>
          <small>careers@coralpetal.ai</small>
        </aside>
      </div>
    </section>
'''

(C / "index.html").write_text(page("Careers at Coral Petal AI", "Careers at Coral Petal AI: remote roles in Agentic AI engineering, data science, cloud, teaching and content.", "/", home, "home"), encoding="utf-8")
(C / "openings/index.html").write_text(page("Openings | Coral Petal AI Careers", "Open roles at Coral Petal AI. All roles are fully remote.", "/openings/", openings, "openings"), encoding="utf-8")
(C / "video-editor/index.html").write_text(page("Video Editor | Coral Petal AI Careers", "Video Editor at Coral Petal AI: enterprise-grade video for courses and clients, produced alongside AI agents. Fully remote.", "/video-editor/", jd, "openings"), encoding="utf-8")
print("built careers pages")
