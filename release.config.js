module.exports = {
  plugins: [
    ["@semantic-release/commit-analyzer", { preset: "conventionalcommits" }],
    [
      "@semantic-release/release-notes-generator",
      { preset: "conventionalcommits" },
    ],
    "@semantic-release/changelog",
    [
      "semantic-release-plugin-update-version-in-files",
      {
        files: [
          "ledyer-checkout-for-woocommerce.php",
          "readme.txt",
          "classes/class-ledyer-main.php",
          "languages/ledyer-checkout-for-woocommerce.pot",
        ],
        placeholder: "0.0.0-development",
      },
    ],
    [
      "@semantic-release/git",
      {
        assets: [
          "ledyer-checkout-for-woocommerce.php",
          "readme.txt",
          "classes/class-ledyer-main.php",
          "languages/ledyer-checkout-for-woocommerce.pot",
          "CHANGELOG.md",
        ],
        message:
          "chore(release): ${nextRelease.version} [skip ci]\n\n${nextRelease.notes}",
      },
    ],
    "@semantic-release/github",
  ],
};
