{ pkgs, lib, stdenv, fetchFromGitHub, fetchurl
, makeWrapper
, php82, php82Packages
, ffmpeg_5-headless, mp4v2, fdk_aac, fdk-aac-encoder
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

  m4bToolFfmpeg = ffmpeg_5-headless.overrideAttrs (prev: rec {
    configureFlags = prev.configureFlags ++ [
      "--enable-libfdk-aac"
      "--enable-nonfree"
    ];
    buildInputs = prev.buildInputs ++ [
      fdk_aac
    ];
  });
in
m4bToolComposer.overrideAttrs (prev: rec {
  version = "0.5";

  buildInputs = [
    m4bToolPhp m4bToolFfmpeg mp4v2 fdk-aac-encoder
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

  installCheckPhase = let
    exampleAudiobook = fetchurl {
      name = "audiobook";
      url = "https://archive.org/download/M4bCollectionOfLibrivoxAudiobooks/ArtOfWar-64kb.m4b";
      sha256 = "00cvbk2a4iyswfmsblx2h9fcww2mvb4vnlf22gqgi1ldkw67b5w7";
    };
  in ''
    # Run the unit test suite
    php vendor/bin/phpunit tests

    # Check that the audiobook split actually works
    (
      cd /tmp

      cp ${exampleAudiobook} audiobook.m4b
      $out/bin/m4b-tool split -vvv -o . audiobook.m4b

      if ! grep -q 'The Nine Situations' audiobook.chapters.txt; then
        exit 1
      fi

      if [ ! -f '006-11 The Nine Situations.m4b' ]; then
        exit 1
      fi
    )
  '';

  passthru = {
    dependencies = buildInputs;
    devDependencies = nativeBuildInputs;
  };
})
