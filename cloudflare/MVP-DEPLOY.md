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

## Enable OpenAI answers and grading

The Worker sends learner questions and open-ended answers to the OpenAI Responses API when a secret key is configured. The key is read only by the Worker and must never be added to GitHub or the browser. From this `cloudflare` directory, run:

```powershell
node .\node_modules\wrangler\bin\wrangler.js secret put OPENAI_API_KEY
```

Wrangler prompts for the key without placing it in the command. Paste it at the prompt and press Enter. Create an API key in your OpenAI Platform account first; API usage may incur charges separate from a ChatGPT subscription. Set API usage alerts or limits in the Platform. To use a different supported model, configure the non-secret `OPENAI_MODEL` Worker variable and redeploy; the default is `gpt-4o`.

If the secret has not been configured yet, the app's built-in offline responses remain available. Rotate or revoke a key from the OpenAI Platform if it is ever exposed.

Wrangler prints the persistent `workers.dev` site address after deployment. Share that address with `?course=1` appended to open directly to the embedded Articulate course.
