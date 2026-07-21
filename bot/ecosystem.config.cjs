// pm2 process definition for the gateway bot.
//   pm2 start ecosystem.config.cjs   (from this folder)
//   pm2 status | pm2 logs vsa-bot | pm2 restart vsa-bot
// .cjs because the package is type:module and pm2 loads config via require().
module.exports = {
  apps: [
    {
      name: "vsa-bot",
      script: "src/index.js",
      node_args: "--env-file=.env",
      cwd: __dirname,
      autorestart: true,
      restart_delay: 5000,
      max_restarts: 20,
    },
  ],
};
