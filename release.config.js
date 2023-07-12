module.exports = {
  branches: [
    "main",
    { name: "alpha", prerelease: "alpha" },
    { name: "beta", prerelease: "beta" },
  ],
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
    "semantic-release-export-data",
    "@semantic-release/github",
  ],
};
