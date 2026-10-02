# Longer lasting MVP link

This deployment serves the MVP from a Cloudflare Worker `workers.dev` URL. Learner progress remains in each learner's browser; there is no shared gradebook or account database. Articulate course files are served from R2.

## One-time account setup

1. In Cloudflare, open **R2 Object Storage** and enable R2 for the account. The Articulate package is about 341 MB, so it cannot be included in Workers static assets. R2's published free tier includes 10 GB-month of storage; this package fits inside that storage allowance. Check the account billing screen before enabling any paid options.
2. Reauthorize Wrangler with Workers and R2 permissions by running `node .\node_modules\wrangler\bin\wrangler.js login` in this directory and approving the Cloudflare access request.

## Deploy

From this directory, with Wrangler authenticated and R2 enabled:

```powershell
node .\node_modules\wrangler\bin\wrangler.js r2 bucket create northstar-scorm-courses
node .\node_modules\wrangler\bin\wrangler.js deploy
```

Then upload the bundled Storyline files:

```powershell
.\upload-mvp-course.ps1
```

Wrangler prints the persistent `workers.dev` site address after deployment. Share that address with `?course=1` appended to open directly to the embedded Articulate course.
