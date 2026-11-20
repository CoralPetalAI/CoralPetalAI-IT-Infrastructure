#!/usr/bin/env python3
"""Generate the four service detail pages into site-data/pages/ (2026-11-18, Rohit: one page per service,
/services/ stays the overview). Each page reuses the illustration from its section on services.html.

  python3 scripts/build_service_pages.py && python3 scripts/build_site_pages.py
"""
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
PAGES = ROOT / "site-data" / "pages"
SRC = (PAGES / "services.html").read_text(encoding="utf-8")

ICO = {
    "check": '<path d="M5 12l4 4 10-10"/>',
    "map": '<path d="M9 4 3 6v14l6-2 6 2 6-2V4l-6 2z"/><path d="M9 4v14M15 6v14"/>',
    "chart": '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
    "layers": '<path d="M12 3 2 8l10 5 10-5z"/><path d="M2 13l10 5 10-5"/>',
    "plug": '<path d="M9 7V3M15 7V3M7 7h10v4a5 5 0 0 1-10 0z"/><path d="M12 16v5"/>',
    "shield": '<path d="M12 3l7 3v5c0 4.5-3 8-7 10-4-2-7-5.5-7-10V6z"/><path d="M9 12l2 2 4-4"/>',
    "eye": '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    "users": '<circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><circle cx="17" cy="9" r="2.5"/><path d="M15.5 14.2c2.8.2 5.5 2.2 5.5 5.8"/>',
    "book": '<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2z"/><path d="M4 19V5"/>',
    "bolt": '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>',
    "loop": '<path d="M4 12a8 8 0 0 1 14-5.3L20 9"/><path d="M20 4v5h-5"/><path d="M20 12a8 8 0 0 1-14 5.3L4 15"/><path d="M4 20v-5h5"/>',
    "doc": '<path d="M6 3h9l5 5v13H6z"/><path d="M14 3v6h6M9 13h8M9 17h6"/>',
}
TONES = ["is-coral", "is-blue", "is-green", "is-violet"]

SERVICES = [
    {
        "slug": "strategy", "num": "01", "name": "AI strategy & readiness",
        "title": "AI Strategy & Readiness | Coral Petal AI",
        "desc": "Find the Agentic AI use cases worth doing first, check your data and systems are ready, and leave with a costed roadmap.",
        "h1": "Start with the problems worth solving.",
        "lede": "In a few weeks we find where agents will pay back first, check what your data and systems need, and hand you a roadmap you can fund.",
        "get": [("map", "Ranked use-case portfolio", "Every candidate process scored on value, feasibility and risk, with the first three to start."),
                ("check", "Readiness assessment", "Data, systems, security and skills reviewed, with gaps and fixes listed."),
                ("layers", "Reference architecture", "Models, tools, data flows and controls that fit your cloud and policies."),
                ("doc", "Costed roadmap", "Phases, effort, run costs and the business case, ready for your leadership.")],
        "steps": [("Listen", "Workshops with business and IT owners"), ("Map", "Processes, data and pain points"),
                  ("Score", "Value, feasibility and risk"), ("Plan", "Roadmap, costs and next steps")],
        "fit": ["You know AI matters but not where to start", "You have many ideas and need to pick the right few",
                "Leadership asks for a business case before funding", "You want an independent view of readiness and risk"],
        "faq": [("How long does it take?", "Usually two to four weeks, depending on how many teams and processes are in scope."),
                ("Do we need clean data first?", "No. Part of the work is finding out what your data can support today and what to fix first."),
                ("What happens after the roadmap?", "You can build with your own team, with another partner, or with us. The roadmap is yours either way.")],
    },
    {
        "slug": "implementation", "num": "02", "name": "Agentic AI implementation",
        "title": "Agentic AI Implementation | Coral Petal AI",
        "desc": "Agents that reason, plan and use your tools, built end to end and integrated with your ERP, CRM and data, with people in the loop.",
        "h1": "Agents that act inside your systems.",
        "lede": "We design, build and integrate agents that read, decide and act in your real workflows, with humans approving what matters.",
        "get": [("bolt", "Working agents", "Single or multi-agent workflows that complete real tasks end to end."),
                ("book", "Retrieval over your data", "Answers grounded in your documents, policies and records, with sources."),
                ("plug", "System integration", "Connected to ERP, CRM, email, ticketing and your APIs, on Azure and other major clouds."),
                ("users", "Human in the loop", "Approvals, escalations and audit trails designed in from the start.")],
        "steps": [("Design", "Task, tools, data and controls"), ("Prototype", "A working agent on your data"),
                  ("Harden", "Tests, security and integration"), ("Launch", "Rollout with your teams")],
        "fit": ["A clear process eats hours of skilled time every week", "Work arrives by email, forms or documents and needs judgment",
                "You need answers grounded in your own knowledge", "A pilot worked and now has to run in production"],
        "faq": [("Which models and clouds do you use?", "We pick per use case and policy: Azure OpenAI and other leading models, on Azure, AWS or Google Cloud."),
                ("Does our data leave our environment?", "We design for your data to stay in your cloud tenancy wherever possible, with access you control."),
                ("How fast can we see something working?", "Typically a working prototype on your data within weeks, then hardening for production.")],
    },
    {
        "slug": "governance", "num": "03", "name": "Run & govern",
        "title": "Run & Govern Agentic AI | Coral Petal AI",
        "desc": "Evaluation, monitoring and guardrails that keep agents accurate, secure, compliant and affordable after launch.",
        "h1": "Built for the day after launch.",
        "lede": "Agents change as data, models and users change. We keep them accurate, safe and affordable, and show you the numbers.",
        "get": [("check", "Evaluation suites", "Test sets and scoring that catch regressions before your users do."),
                ("chart", "Quality, cost and drift monitoring", "Dashboards for accuracy, cost per task, latency and escalations."),
                ("shield", "Policy and privacy guardrails", "Rules for sensitive data, actions and content, enforced and logged."),
                ("loop", "Continuous improvement", "Regular reviews, fixes and a clean handover to your team when you are ready.")],
        "steps": [("Baseline", "Measure quality and cost today"), ("Guard", "Set policies and controls"),
                  ("Watch", "Monitor and alert"), ("Improve", "Review, retrain, hand over")],
        "fit": ["Agents are live, but nobody can say how well they work", "Costs are rising faster than value",
                "Audit, risk or compliance teams need evidence", "You plan to scale from one agent to many"],
        "faq": [("Can you govern agents someone else built?", "Yes. We start with an assessment and add evaluation and monitoring around what you already run."),
                ("Which rules do you help with?", "Your internal policies and the data-protection rules that apply to you, such as India’s DPDP Act."),
                ("Do we get locked in?", "No. Tests, dashboards and runbooks are documented and handed over to your team.")],
    },
    {
        "slug": "enablement", "num": "04", "name": "Team enablement",
        "title": "Team Enablement & AI Training | Coral Petal AI",
        "desc": "Your teams learn Generative and Agentic AI and how enterprises implement it, through the Coral Petal AI Academy and live corporate training.",
        "h1": "People who can build and use agents.",
        "lede": "Tools change fast; capable people last. We train your teams on the concepts and on how enterprises really implement them.",
        "get": [("book", "The Signature Course", "End-to-end Generative and Agentic AI, self-paced, in the Coral Petal AI Academy."),
                ("users", "Live classes and bootcamps", "Instructor-led sessions with hands-on builds and Q&A."),
                ("layers", "Corporate training", "Programs shaped around your stack, your use cases and your roles."),
                ("eye", "Leadership briefings", "What agents can and cannot do, risks, and how to lead adoption.")],
        "steps": [("Assess", "Roles, skills and goals"), ("Plan", "Learning paths per team"),
                  ("Train", "Courses, live sessions, builds"), ("Apply", "Projects on your real work")],
        "fit": ["Your teams will build or run agents and need the skills", "Leaders need a clear picture before they invest",
                "You want training tied to real projects, not slides", "You are rolling out AI tools and want them used well"],
        "faq": [("Self-paced or live?", "Both. Many teams take the Signature Course self-paced and add live sessions for their own use cases."),
                ("Can training be customized for our company?", "Yes. Corporate programs use your tools, data examples and roles."),
                ("Where do individuals sign up?", "In the Coral Petal AI Academy. Teams can write to academy@coralpetal.ai.")],
    },
]


def figure(slug):
    m = re.search(r'<section class="section[^"]*" id="' + slug + r'">.*?(<figure class="viz".*?</figure>)', SRC, re.S)
    return m.group(1)


def ico(name, tone):
    return f'<span class="ico-soft {tone}"><svg viewBox="0 0 24 24">{ICO[name]}</svg></span>'


def page(s):
    others = "".join(
        f'<a class="tile" href="/services/{o["slug"]}/"><span class="pill-s p-grey tag" style="margin:0 0 10px">{o["num"]}</span><h3 style="margin-top:0">{o["name"].replace("&", "&amp;")}</h3><p>{o["h1"]}</p></a>'
        for o in SERVICES if o is not s)
    get = "".join(f'<article class="tile">{ico(i, TONES[n % 4])}<h3>{t}</h3><p>{d}</p></article>' for n, (i, t, d) in enumerate(s["get"]))
    steps = "".join(f'<li><span class="dot{" dot-hl" if n == 3 else ""}">{n + 1}</span><b>{a}</b><small>{b}</small></li>' for n, (a, b) in enumerate(s["steps"]))
    fit = "".join(f"<li>{x}</li>" for x in s["fit"])
    faq = "".join(f'<details class="svc-faq"><summary>{q}</summary><p>{a}</p></details>' for q, a in s["faq"])
    extra = '<a class="btn btn-ghost" href="https://academy.coralpetal.ai/">Visit the Academy</a>' if s["slug"] == "enablement" else ""
    name = s["name"].replace("&", "&amp;")
    return f"""<!--
path: services/{s["slug"]}/
nav: services
title: {s["title"]}
description: {s["desc"]}
-->
    <section class="page-hero">
      <div class="wrap ph-grid">
        <div>
          <p class="eyebrow"><a href="/services/">Services</a> · {s["num"]} · {name}</p>
          <h1>{s["h1"]}</h1>
          <p class="lede">{s["lede"]}</p>
          <div class="cta-row"><a class="btn" href="/contact/">Start a conversation</a>{extra}</div>
        </div>
        {figure(s["slug"])}
      </div>
    </section>

    <section class="section">
      <div class="wrap">
        <div class="section-head"><p class="eyebrow chip">What you get</p><h2>Clear deliverables, no black boxes.</h2></div>
        <div class="grid g4 tiles svc-tiles">{get}</div>
      </div>
    </section>

    <section class="section section-alt">
      <div class="wrap">
        <div class="section-head"><p class="eyebrow chip">How it works</p><h2>Four steps, each with something you can see.</h2></div>
        <ol class="flow">{steps}</ol>
      </div>
    </section>

    <section class="section">
      <div class="wrap svc-two">
        <div class="card coral-card svc-fit"><h2>A good fit when</h2><ul class="cr-checks">{fit}</ul></div>
        <div><h2>Questions we hear</h2>{faq}</div>
      </div>
    </section>

    <section class="section" style="padding-top:0">
      <div class="wrap">
        <div class="section-head"><p class="eyebrow chip">More services</p></div>
        <div class="grid g3 tiles">{others}</div>
      </div>
    </section>

    <section class="section" style="padding-top:0">
      <div class="wrap">
        <div class="cta-band">
          <h2>Have a process an agent could run?</h2>
          <p>Tell us about it. We’ll come back with an honest view of what’s possible.</p>
          <div class="cta-row"><a class="btn" href="/contact/">Contact us</a></div>
        </div>
      </div>
    </section>
"""


def main():
    for s in SERVICES:
        (PAGES / f"service-{s['slug']}.html").write_text(page(s), encoding="utf-8")
    print(f"wrote {len(SERVICES)} service pages")


if __name__ == "__main__":
    main()
