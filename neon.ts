import { defineConfig } from "@neon/config/v1";

export default defineConfig({
  // Declare your Neon services here
  preview: {
    realtime: { allowedOrigins: ["http://127.0.0.1:8080", "http://localhost:8080"] },
  },
  auth: true,
  functions: {
    hello: {
      name: "Yogee reservation services", source: "./hello.ts",
      env: { NEON_REALTIME_SECRET: process.env.NEON_REALTIME_SECRET!, ORIGIN_SECRET: process.env.ORIGIN_SECRET! },
    },
  },
  buckets: {
    // "private" is the default; use "public_read" for anonymous reads
    assets: { access: "private" },
  },
  // Branch policy: per-branch tuning
  branch: (branch) => {
    if (branch.isDefault) {
      // Default branch: no overrides, uses project defaults
      return {};
    }
    if (!branch.exists) {
      // New non-default branches: auto-expire
      // Run `neon checkout <name>` to create a new branch with these settings
      return { ttl: "7d" };
    }
    // Existing branch: no changes
    return {};
  },
});
