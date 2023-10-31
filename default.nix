{ pkgs, lib, stdenv, fetchFromGitHub, fetchurl
, runtimeShell
, php82, php82Packages
, ffmpeg_5-headless, mp4v2, fdk_aac, fdk-aac-encoder
, useLibfdkFfmpeg ? false
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

  m4bToolFfmpeg = if useLibfdkFfmpeg then ffmpeg_5-headless.overrideAttrs (prev: rec {
    configureFlags = prev.configureFlags ++ [
      "--enable-libfdk-aac"
      "--enable-nonfree"
    ];
    buildInputs = prev.buildInputs ++ [
      fdk_aac
    ];
  }) else ffmpeg_5-headless;
in
m4bToolComposer.overrideAttrs (prev: rec {
  pname = "m4b-tool";
  version = "0.5";

  buildInputs = [
    m4bToolPhp m4bToolFfmpeg mp4v2 fdk-aac-encoder
  ];

  nativeBuildInputs = [
    m4bToolPhp m4bToolPhpPackages.composer
  ];

  postInstall = ''
    # Fix the version
    sed -i 's!@package_version@!${version}!g' bin/m4b-tool.php
  '';

  postFixup = ''
    # Wrap it
    rm -rf $out/bin
    mkdir -p $out/bin

    # makeWrapper fails for this on macOS
    cat >$out/bin/m4b-tool <<EOF
    #!${runtimeShell}
    export PATH=${lib.makeBinPath buildInputs}
    export M4B_TOOL_DISABLE_TONE=true
    exec ${m4bToolPhp}/bin/php $out/share/php/sandreas-m4b-tool/bin/m4b-tool.php "\$@"
    EOF
    chmod +x $out/bin/m4b-tool
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
      mkdir -p audiobook
      cd audiobook

      cp ${exampleAudiobook} audiobook.m4b
      $out/bin/m4b-tool split -vvv -o . audiobook.m4b

      if ! grep -q 'The Nine Situations' audiobook.chapters.txt; then
        exit 1
      fi

      if [ ! -f '006-11 The Nine Situations.m4b' ]; then
        exit 1
      fi
    )
    rm -rf audiobook
  '';

  passthru = {
    dependencies = buildInputs;
    devDependencies = nativeBuildInputs;
  };
})
