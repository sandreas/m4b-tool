{ pkgs, lib, stdenv, fetchFromGitHub
, makeWrapper
, php82, php82Packages
, ffmpeg-headless, mp4v2, fdk-aac-encoder
}:

let
  m4bToolPhp = php82.buildEnv {
    extensions = ({ enabled, all }: enabled ++ (with all; [
      dom mbstring tokenizer xmlwriter openssl
    ]));

    extraConfig = ''
      date.timezone = UTC
      error_reporting = E_ALL & ~E_STRICT & ~E_NOTICE & ~E_DEPRECATED
    '';
  };

  m4bToolPhpPackages = php82Packages;

  m4bToolComposer = pkgs.callPackage ./composer.nix {
    php = m4bToolPhp;
    phpPackages = m4bToolPhpPackages;
  };
in
m4bToolComposer.overrideAttrs (prev: rec {
  version = "0.5";

  buildInputs = [
    m4bToolPhp ffmpeg-headless mp4v2 fdk-aac-encoder
  ];

  nativeBuildInputs = [
    m4bToolPhp m4bToolPhpPackages.composer makeWrapper
  ];

  postInstall = ''
    # Fix the version
    sed -i 's!@package_version@!${version}!g' bin/m4b-tool.php
  '';

  postFixup = ''
    # Wrap it
    rm -rf $out/bin
    mkdir -p $out/bin

    makeWrapper \
      $out/share/php/m4b-tool/bin/m4b-tool.php \
      $out/bin/m4b-tool \
      --set PATH ${lib.makeBinPath buildInputs} \
      --set M4B_TOOL_DISABLE_TONE true
  '';

  doInstallCheck = true;

  installCheckPhase = ''
    php vendor/bin/phpunit tests
  '';

  passthru = {
    dependencies = buildInputs;
    devDependencies = nativeBuildInputs;
  };
})
