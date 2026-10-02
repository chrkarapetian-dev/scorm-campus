# Free Cloudflare deployment

This version serves the course site from Cloudflare Workers and stores SCORM files in a private R2 bucket. It uses the included `workers.dev` address, so no domain is needed. Uploaded courses are public to anyone with the generated link. Learner progress is stored in that learner's browser; this prototype has no accounts or central gradebook.

## Deploy

1. Install Node.js 20 or later.
2. Open a terminal in this folder and run `npm install`.
3. Sign in to Cloudflare in the browser, then run `npx wrangler login` in the terminal and approve the Cloudflare login.
4. Create the private bucket with `npx wrangler r2 bucket create northstar-scorm-courses`.
5. Set a strong upload password with `npx wrangler secret put UPLOAD_TOKEN`.
6. Deploy with `npm run deploy`.
7. Open the `workers.dev` address printed by Wrangler. Use **Add SCORM course**, enter the upload password, and copy the generated course link.

Cloudflare's current R2 free tier includes 10 GB-month storage, 1 million Class A operations, 10 million Class B operations, and free egress. Workers Free includes up to 100,000 requests per day. Provider quotas and terms can change; large packages or high learner traffic may exceed free usage.
