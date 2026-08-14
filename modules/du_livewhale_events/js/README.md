# LiveWhale loading behavior

`du-livewhale-events-loading.js` coordinates the local loading indicator with
markup and CSS that LiveWhale injects asynchronously. It does not fetch,
transform, or replace LiveWhale responses.

## Execution order

The `du_livewhale_events/loading` Drupal library depends on `core/drupal`,
`core/once`, and `du_livewhale_events/base`. When loading enhancement is
enabled, the dynamically built `du_livewhale_events/widget` library depends on
the loading library before it loads LiveWhale's external `lwcw.js` script.

That order lets this script establish the placeholder before LiveWhale begins
populating each `.lwcw` element. If the loading JavaScript does not run, the
CSS leaves the placeholder hidden and does not hide `.lwcw`, providing a basic
fail-open path.

## Initialization

The script registers a Drupal behavior and also calls `attach(document)`
immediately. The immediate call covers the initial page load before the
external loader executes. The Drupal behavior covers content processed through
Drupal's behavior system.

`once()` ensures that a container matching `[data-du-livewhale-loading]` is
initialized only once, including when Drupal behaviors are attached more than
once. Each container has independent observers and a timeout, so several
LiveWhale Paragraphs can load at different speeds on the same page.

Initialization adds:

- `du-livewhale-events--loading`, which lets the local CSS show the placeholder
  and temporarily remove the injected widget from layout;
- `aria-busy="true"`, which exposes the pending state to assistive technology;
- a ten-second fail-open timer.

The configured loading message is already present in the Twig markup. The
visible placeholder is `aria-hidden`; an adjacent visually hidden live region
announces the same message without also announcing the decorative spinner.

## Readiness detection

LiveWhale changes two separate parts of the document:

1. It injects event markup into the widget container.
2. It adds its generated stylesheet to the document head.

The script therefore observes both the `.lwcw` subtree and `document.head`.
After either changes, `checkReady()` requires both of these signals:

- the widget contains meaningful injected content, excluding asset-only
  `<link>`, `<script>`, `<style>`, `<noscript>`, and `<template>` children; and
- at least one stylesheet whose URL contains `/live/resource/css/` has loaded.

The content check intentionally does not depend on a class from any one
LiveWhale widget template. The `.lwcw` container is initially empty, so a
non-asset element child or non-empty text signals that LiveWhale has populated
the widget while allowing different display types to use different markup.

For a stylesheet that is already ready, the browser exposes a truthy
`stylesheet.sheet`. For a stylesheet still loading, the script registers a
one-time `load` listener. Content by itself is not considered ready because
revealing LiveWhale's raw markup before its CSS arrives causes the second layout
shift this behavior is intended to avoid.

Once both signals exist, the script waits for two consecutive
`requestAnimationFrame()` callbacks. The first gives the browser an opportunity
to apply the remote stylesheet; the second reveals the widget after that style
work has reached a paint boundary.

## Completion states

Only the first completion path changes the container. The internal `complete`
flag makes later observer notifications, stylesheet events, or queued animation
frames no-ops.

| State | Container classes | `aria-busy` | Live-region message |
| --- | --- | --- | --- |
| Loading | `--loading` | `true` | Configured loading text |
| Ready | `--loaded` | `false` | `Events loaded.` |
| Ten-second timeout | `--timed-out` | `false` | `Events are taking longer than expected to load.` |

Both completion paths disconnect the mutation observers. The ready path also
cancels the timeout. On timeout, the loading class is removed so LiveWhale
content is no longer hidden, even when a selector or remote-stylesheet change
prevents normal readiness detection.

The enhancement reduces layout shifts but cannot guarantee a zero CLS score.
The configured minimum height is only a floor; final responsive content can be
taller, and fonts, images, pagination, or later LiveWhale updates can change the
widget after it is revealed.

## Contracts and maintenance

The script intentionally depends on three integration details:

- Drupal renders `[data-du-livewhale-loading]` around one `.lwcw` widget.
- LiveWhale injects meaningful content directly into the initially empty
  `.lwcw` container.
- LiveWhale's injected stylesheet URL contains `/live/resource/css/`.

If LiveWhale changes its output, verify those selectors before increasing the
timeout. A consistent ten-second delay usually means one readiness signal is
missing, not that the timeout is too short.

When debugging, inspect the container classes and `aria-busy`, confirm that a
non-asset child or non-empty text exists in `.lwcw`, and check the document head
and Network panel for the matching stylesheet. The Playwright coverage delays
the real widget request without mocking its response so upstream integration
failures remain visible.
