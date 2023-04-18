{composerEnv, fetchurl, fetchgit ? null, fetchhg ? null, fetchsvn ? null, noDev ? false}:

let
  packages = {
    "bluemoehre/flac-php" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "bluemoehre-flac-php-0194a5090124983d5dd8e72440d04955da0abc42";
        src = fetchurl {
          url = "https://api.github.com/repos/bluemoehre/flac-php/zipball/0194a5090124983d5dd8e72440d04955da0abc42";
          sha256 = "0zfd6bk8358rhax4fjfnlncgcmsnkp1jnxpb7qjnwh1nmjwb7hjf";
        };
      };
    };
    "doctrine/collections" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "doctrine-collections-2b44dd4cbca8b5744327de78bafef5945c7e7b5e";
        src = fetchurl {
          url = "https://api.github.com/repos/doctrine/collections/zipball/2b44dd4cbca8b5744327de78bafef5945c7e7b5e";
          sha256 = "0ch52jxr7kpv64dmg4xbzlnsspsc0ykm227h2hvr40clalhdw6n8";
        };
      };
    };
    "doctrine/deprecations" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "doctrine-deprecations-0e2a4f1f8cdfc7a92ec3b01c9334898c806b30de";
        src = fetchurl {
          url = "https://api.github.com/repos/doctrine/deprecations/zipball/0e2a4f1f8cdfc7a92ec3b01c9334898c806b30de";
          sha256 = "1sk1f020n0w7p7r4rsi7wnww85vljrim1i5h9wb0qiz2c4l8jj09";
        };
      };
    };
    "erusev/parsedown" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "erusev-parsedown-cb17b6477dfff935958ba01325f2e8a2bfa6dab3";
        src = fetchurl {
          url = "https://api.github.com/repos/erusev/parsedown/zipball/cb17b6477dfff935958ba01325f2e8a2bfa6dab3";
          sha256 = "1iil9v8g03m5vpxxg3a5qb2sxd1cs5c4p5i0k00cqjnjsxfrazxd";
        };
      };
    };
    "lywzx/php-epub" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "lywzx-php-epub-ad1a1173d97ff830ad6a7cd7be3164c6b20e60ed";
        src = fetchurl {
          url = "https://api.github.com/repos/lywzx/php-epub/zipball/ad1a1173d97ff830ad6a7cd7be3164c6b20e60ed";
          sha256 = "0f4yp0qvj2fiag3ak6hzcqlj5svqvaxhw9dq56sc7jf2rpjm5p45";
        };
      };
    };
    "monolog/monolog" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "monolog-monolog-904713c5929655dc9b97288b69cfeedad610c9a1";
        src = fetchurl {
          url = "https://api.github.com/repos/Seldaek/monolog/zipball/904713c5929655dc9b97288b69cfeedad610c9a1";
          sha256 = "17fjd5dk45b6dbfx15vxqk6mnm3fsn2kd8nsjfjd2zk3zfihq4jj";
        };
      };
    };
    "psr/cache" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "psr-cache-213f9dbc5b9bfbc4f8db86d2838dc968752ce13b";
        src = fetchurl {
          url = "https://api.github.com/repos/php-fig/cache/zipball/213f9dbc5b9bfbc4f8db86d2838dc968752ce13b";
          sha256 = "0iv5miiz152y48zxdkw97v7swwpc5mixaj9iynm2bq52skvcczjb";
        };
      };
    };
    "psr/container" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "psr-container-513e0666f7216c7459170d56df27dfcefe1689ea";
        src = fetchurl {
          url = "https://api.github.com/repos/php-fig/container/zipball/513e0666f7216c7459170d56df27dfcefe1689ea";
          sha256 = "00yvj3b5ls2l1d0sk38g065raw837rw65dx1sicggjnkr85vmfzz";
        };
      };
    };
    "psr/http-message" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "psr-http-message-f6561bf28d520154e4b0ec72be95418abe6d9363";
        src = fetchurl {
          url = "https://api.github.com/repos/php-fig/http-message/zipball/f6561bf28d520154e4b0ec72be95418abe6d9363";
          sha256 = "195dd67hva9bmr52iadr4kyp2gw2f5l51lplfiay2pv6l9y4cf45";
        };
      };
    };
    "psr/log" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "psr-log-d49695b909c3b7628b6289db5479a1c204601f11";
        src = fetchurl {
          url = "https://api.github.com/repos/php-fig/log/zipball/d49695b909c3b7628b6289db5479a1c204601f11";
          sha256 = "0sb0mq30dvmzdgsnqvw3xh4fb4bqjncx72kf8n622f94dd48amln";
        };
      };
    };
    "sandreas/php-strings" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "sandreas-php-strings-971f8f94619655b186332eed4218577d7069cab1";
        src = fetchurl {
          url = "https://api.github.com/repos/sandreas/php-strings/zipball/971f8f94619655b186332eed4218577d7069cab1";
          sha256 = "065lvkzdldgqr1hbdd85l2yf0q3w29aiy9xzm1vqk9b56mnf1416";
        };
      };
    };
    "sandreas/php-time" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "sandreas-php-time-e0bcc91af8d14e21445eb167050e58af205152fd";
        src = fetchurl {
          url = "https://api.github.com/repos/sandreas/php-time/zipball/e0bcc91af8d14e21445eb167050e58af205152fd";
          sha256 = "0bd4q9n72k0ni0nqp82idsn9hdx5drxdsgq9kpw7xqhp4p9a510m";
        };
      };
    };
    "symfony/cache" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "symfony-cache-3b98ed664887ad197b8ede3da2432787212eb915";
        src = fetchurl {
          url = "https://api.github.com/repos/symfony/cache/zipball/3b98ed664887ad197b8ede3da2432787212eb915";
          sha256 = "1a3jpcnqb9mn6aljh9d0ga9kdi4vp2lsrz20ir5v0r0zn4kjgbfi";
        };
      };
    };
    "symfony/cache-contracts" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "symfony-cache-contracts-64be4a7acb83b6f2bf6de9a02cee6dad41277ebc";
        src = fetchurl {
          url = "https://api.github.com/repos/symfony/cache-contracts/zipball/64be4a7acb83b6f2bf6de9a02cee6dad41277ebc";
          sha256 = "1qks21c3vfdns0iln9qafvs00qjwh2jg3kp1zflx6bb3bfm6dl5x";
        };
      };
    };
    "symfony/console" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "symfony-console-33fa45ffc81fdcc1ca368d4946da859c8cdb58d9";
        src = fetchurl {
          url = "https://api.github.com/repos/symfony/console/zipball/33fa45ffc81fdcc1ca368d4946da859c8cdb58d9";
          sha256 = "17mqfqw96xqa2fj5697jhnwbzybgxvh7inr4fpbphizhyk51jkxm";
        };
      };
    };
    "symfony/deprecation-contracts" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "symfony-deprecation-contracts-1ee04c65529dea5d8744774d474e7cbd2f1206d3";
        src = fetchurl {
          url = "https://api.github.com/repos/symfony/deprecation-contracts/zipball/1ee04c65529dea5d8744774d474e7cbd2f1206d3";
          sha256 = "0clwxx1aaf54ajk5byr95gzdvxpfy4s9alkarwykjcga81b5grn7";
        };
      };
    };
    "symfony/polyfill-ctype" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "symfony-polyfill-ctype-5bbc823adecdae860bb64756d639ecfec17b050a";
        src = fetchurl {
          url = "https://api.github.com/repos/symfony/polyfill-ctype/zipball/5bbc823adecdae860bb64756d639ecfec17b050a";
          sha256 = "0vyv70z1yi2is727d1mkb961w5r1pb1v3wy1pvdp30h8ffy15wk6";
        };
      };
    };
    "symfony/polyfill-mbstring" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "symfony-polyfill-mbstring-8ad114f6b39e2c98a8b0e3bd907732c207c2b534";
        src = fetchurl {
          url = "https://api.github.com/repos/symfony/polyfill-mbstring/zipball/8ad114f6b39e2c98a8b0e3bd907732c207c2b534";
          sha256 = "1ym84qp609i50lv4vkd4yz99y19kaxd5kmpdnh66mxx1a4a104mi";
        };
      };
    };
    "symfony/polyfill-php73" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "symfony-polyfill-php73-9e8ecb5f92152187c4799efd3c96b78ccab18ff9";
        src = fetchurl {
          url = "https://api.github.com/repos/symfony/polyfill-php73/zipball/9e8ecb5f92152187c4799efd3c96b78ccab18ff9";
          sha256 = "1p0jr92x323pl4frjbhmziyk5g1zig1g30i1v1p0wfli2sq8h5mb";
        };
      };
    };
    "symfony/polyfill-php80" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "symfony-polyfill-php80-7a6ff3f1959bb01aefccb463a0f2cd3d3d2fd936";
        src = fetchurl {
          url = "https://api.github.com/repos/symfony/polyfill-php80/zipball/7a6ff3f1959bb01aefccb463a0f2cd3d3d2fd936";
          sha256 = "16yydk7rsknlasrpn47n4b4js8svvp4rxzw99dkav52wr3cqmcwd";
        };
      };
    };
    "symfony/process" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "symfony-process-5cee9cdc4f7805e2699d9fd66991a0e6df8252a2";
        src = fetchurl {
          url = "https://api.github.com/repos/symfony/process/zipball/5cee9cdc4f7805e2699d9fd66991a0e6df8252a2";
          sha256 = "1rah0j1rbynyjkshaar0jwcm0kl53pixhxxwxc92z2nxp8n1n6z7";
        };
      };
    };
    "symfony/service-contracts" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "symfony-service-contracts-4b426aac47d6427cc1a1d0f7e2ac724627f5966c";
        src = fetchurl {
          url = "https://api.github.com/repos/symfony/service-contracts/zipball/4b426aac47d6427cc1a1d0f7e2ac724627f5966c";
          sha256 = "0lh0vxy0h4wsjmnlf42s950bicsvkzz6brqikfnfb5kmvi0xhcm6";
        };
      };
    };
    "symfony/var-exporter" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "symfony-var-exporter-2a1d06fcf2b30829d6c01dae8e6e188424d1f8f6";
        src = fetchurl {
          url = "https://api.github.com/repos/symfony/var-exporter/zipball/2a1d06fcf2b30829d6c01dae8e6e188424d1f8f6";
          sha256 = "19hgay0mw0m4v36b0lm066bd40ywsi2aa1b7y7kn30s5cjfcsg3j";
        };
      };
    };
    "twig/twig" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "twig-twig-a6e0510cc793912b451fd40ab983a1d28f611c15";
        src = fetchurl {
          url = "https://api.github.com/repos/twigphp/Twig/zipball/a6e0510cc793912b451fd40ab983a1d28f611c15";
          sha256 = "081bhmbawnvfxhwqhmjh0lqiqkilhyj0r3j3m82ybck8yxy96l9b";
        };
      };
    };
    "wapmorgan/binary-stream" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "wapmorgan-binary-stream-ca4989c15801635d79f65426b1b0fc6fe803b620";
        src = fetchurl {
          url = "https://api.github.com/repos/wapmorgan/BinaryStream/zipball/ca4989c15801635d79f65426b1b0fc6fe803b620";
          sha256 = "0mpqiw64c570zcd49sy3p1df4qvjmxz6yfaysdxd09qn4hrdxrs8";
        };
      };
    };
    "wapmorgan/file-type-detector" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "wapmorgan-file-type-detector-1db30747b674fe9444294c5393794d18f712f114";
        src = fetchurl {
          url = "https://api.github.com/repos/wapmorgan/FileTypeDetector/zipball/1db30747b674fe9444294c5393794d18f712f114";
          sha256 = "1zxi040lgx4zyyfqqywwqlkziqy7yibk0ky3v71j0xbxdw8qcaz3";
        };
      };
    };
    "wapmorgan/media-file" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "wapmorgan-media-file-263f773696e02d173fb9636c9a8111ac84032292";
        src = fetchurl {
          url = "https://api.github.com/repos/wapmorgan/MediaFile/zipball/263f773696e02d173fb9636c9a8111ac84032292";
          sha256 = "1l19imzfpff08ic1ddjr57pn2m0sg7sk0i3a9w27bnkd479rway0";
        };
      };
    };
    "wapmorgan/mp3info" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "wapmorgan-mp3info-13d1c142a75ca5bf63dd29d706c9a93d8436dbcb";
        src = fetchurl {
          url = "https://api.github.com/repos/wapmorgan/Mp3Info/zipball/13d1c142a75ca5bf63dd29d706c9a93d8436dbcb";
          sha256 = "18qcmw5cnpww14l1higkqpf6khrmhzc855jraqx9fhqwi1ikzzr2";
        };
      };
    };
  };
  devPackages = {
    "doctrine/instantiator" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "doctrine-instantiator-c6222283fa3f4ac679f8b9ced9a4e23f163e80d0";
        src = fetchurl {
          url = "https://api.github.com/repos/doctrine/instantiator/zipball/c6222283fa3f4ac679f8b9ced9a4e23f163e80d0";
          sha256 = "059ahw73z0m24cal4f805j6h1i53f90mrmjr7s4f45yfxgwcqvcn";
        };
      };
    };
    "hamcrest/hamcrest-php" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "hamcrest-hamcrest-php-8c3d0a3f6af734494ad8f6fbbee0ba92422859f3";
        src = fetchurl {
          url = "https://api.github.com/repos/hamcrest/hamcrest-php/zipball/8c3d0a3f6af734494ad8f6fbbee0ba92422859f3";
          sha256 = "1ixmmpplaf1z36f34d9f1342qjbcizvi5ddkjdli6jgrbla6a6hr";
        };
      };
    };
    "mikey179/vfsstream" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "mikey179-vfsstream-17d16a85e6c26ce1f3e2fa9ceeacdc2855db1e9f";
        src = fetchurl {
          url = "https://api.github.com/repos/bovigo/vfsStream/zipball/17d16a85e6c26ce1f3e2fa9ceeacdc2855db1e9f";
          sha256 = "1vbsc13gkwhhfffwnr0l1j0nyzk4qdgxjhw6wf3yk1vzkjlcxd8m";
        };
      };
    };
    "mockery/mockery" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "mockery-mockery-e92dcc83d5a51851baf5f5591d32cb2b16e3684e";
        src = fetchurl {
          url = "https://api.github.com/repos/mockery/mockery/zipball/e92dcc83d5a51851baf5f5591d32cb2b16e3684e";
          sha256 = "0dvkr0ff37cn6s72s7sqw26j6i5fja780x980zhl099frflkw5s9";
        };
      };
    };
    "myclabs/deep-copy" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "myclabs-deep-copy-14daed4296fae74d9e3201d2c4925d1acb7aa614";
        src = fetchurl {
          url = "https://api.github.com/repos/myclabs/DeepCopy/zipball/14daed4296fae74d9e3201d2c4925d1acb7aa614";
          sha256 = "11593chczjw8k5jix2mj9v31lg5jgpxqrkhp27bxd96aajapqd9w";
        };
      };
    };
    "nikic/php-parser" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "nikic-php-parser-570e980a201d8ed0236b0a62ddf2c9cbb2034039";
        src = fetchurl {
          url = "https://api.github.com/repos/nikic/PHP-Parser/zipball/570e980a201d8ed0236b0a62ddf2c9cbb2034039";
          sha256 = "1q3hnpjya4rsxfh72ccs2vj3gz8h0sn12290w8wvkz9pvi0qmmmr";
        };
      };
    };
    "phar-io/manifest" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "phar-io-manifest-97803eca37d319dfa7826cc2437fc020857acb53";
        src = fetchurl {
          url = "https://api.github.com/repos/phar-io/manifest/zipball/97803eca37d319dfa7826cc2437fc020857acb53";
          sha256 = "107dsj04ckswywc84dvw42kdrqd4y6yvb2qwacigyrn05p075c1w";
        };
      };
    };
    "phar-io/version" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "phar-io-version-4f7fd7836c6f332bb2933569e566a0d6c4cbed74";
        src = fetchurl {
          url = "https://api.github.com/repos/phar-io/version/zipball/4f7fd7836c6f332bb2933569e566a0d6c4cbed74";
          sha256 = "0mdbzh1y0m2vvpf54vw7ckcbcf1yfhivwxgc9j9rbb7yifmlyvsg";
        };
      };
    };
    "phpunit/php-code-coverage" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "phpunit-php-code-coverage-2cf940ebc6355a9d430462811b5aaa308b174bed";
        src = fetchurl {
          url = "https://api.github.com/repos/sebastianbergmann/php-code-coverage/zipball/2cf940ebc6355a9d430462811b5aaa308b174bed";
          sha256 = "1lmj7fqwcbgkbmjq9s9szyq40mbyvk77jjidgf0x2yy6mpnig1za";
        };
      };
    };
    "phpunit/php-file-iterator" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "phpunit-php-file-iterator-cf1c2e7c203ac650e352f4cc675a7021e7d1b3cf";
        src = fetchurl {
          url = "https://api.github.com/repos/sebastianbergmann/php-file-iterator/zipball/cf1c2e7c203ac650e352f4cc675a7021e7d1b3cf";
          sha256 = "1407d8f1h35w4sdikq2n6cz726css2xjvlyr1m4l9a53544zxcnr";
        };
      };
    };
    "phpunit/php-invoker" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "phpunit-php-invoker-5a10147d0aaf65b58940a0b72f71c9ac0423cc67";
        src = fetchurl {
          url = "https://api.github.com/repos/sebastianbergmann/php-invoker/zipball/5a10147d0aaf65b58940a0b72f71c9ac0423cc67";
          sha256 = "1vqnnjnw94mzm30n9n5p2bfgd3wd5jah92q6cj3gz1nf0qigr4fh";
        };
      };
    };
    "phpunit/php-text-template" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "phpunit-php-text-template-5da5f67fc95621df9ff4c4e5a84d6a8a2acf7c28";
        src = fetchurl {
          url = "https://api.github.com/repos/sebastianbergmann/php-text-template/zipball/5da5f67fc95621df9ff4c4e5a84d6a8a2acf7c28";
          sha256 = "0ff87yzywizi6j2ps3w0nalpx16mfyw3imzn6gj9jjsfwc2bb8lq";
        };
      };
    };
    "phpunit/php-timer" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "phpunit-php-timer-5a63ce20ed1b5bf577850e2c4e87f4aa902afbd2";
        src = fetchurl {
          url = "https://api.github.com/repos/sebastianbergmann/php-timer/zipball/5a63ce20ed1b5bf577850e2c4e87f4aa902afbd2";
          sha256 = "0g1g7yy4zk1bidyh165fsbqx5y8f1c8pxikvcahzlfsr9p2qxk6a";
        };
      };
    };
    "phpunit/phpunit" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "phpunit-phpunit-e7b1615e3e887d6c719121c6d4a44b0ab9645555";
        src = fetchurl {
          url = "https://api.github.com/repos/sebastianbergmann/phpunit/zipball/e7b1615e3e887d6c719121c6d4a44b0ab9645555";
          sha256 = "1p7bgvlxaqaw1r3jr8nrz2vbb7sc4fsidcf39i72c58dfrgql93s";
        };
      };
    };
    "sebastian/cli-parser" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "sebastian-cli-parser-442e7c7e687e42adc03470c7b668bc4b2402c0b2";
        src = fetchurl {
          url = "https://api.github.com/repos/sebastianbergmann/cli-parser/zipball/442e7c7e687e42adc03470c7b668bc4b2402c0b2";
          sha256 = "074qzdq19k9x4svhq3nak5h348xska56v1sqnhk1aj0jnrx02h37";
        };
      };
    };
    "sebastian/code-unit" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "sebastian-code-unit-1fc9f64c0927627ef78ba436c9b17d967e68e120";
        src = fetchurl {
          url = "https://api.github.com/repos/sebastianbergmann/code-unit/zipball/1fc9f64c0927627ef78ba436c9b17d967e68e120";
          sha256 = "04vlx050rrd54mxal7d93pz4119pas17w3gg5h532anfxjw8j7pm";
        };
      };
    };
    "sebastian/code-unit-reverse-lookup" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "sebastian-code-unit-reverse-lookup-ac91f01ccec49fb77bdc6fd1e548bc70f7faa3e5";
        src = fetchurl {
          url = "https://api.github.com/repos/sebastianbergmann/code-unit-reverse-lookup/zipball/ac91f01ccec49fb77bdc6fd1e548bc70f7faa3e5";
          sha256 = "1h1jbzz3zak19qi4mab2yd0ddblpz7p000jfyxfwd2ds0gmrnsja";
        };
      };
    };
    "sebastian/comparator" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "sebastian-comparator-fa0f136dd2334583309d32b62544682ee972b51a";
        src = fetchurl {
          url = "https://api.github.com/repos/sebastianbergmann/comparator/zipball/fa0f136dd2334583309d32b62544682ee972b51a";
          sha256 = "0m8ibkwaxw2q5v84rlvy7ylpkddscsa8hng0cjczy4bqpqavr83w";
        };
      };
    };
    "sebastian/complexity" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "sebastian-complexity-739b35e53379900cc9ac327b2147867b8b6efd88";
        src = fetchurl {
          url = "https://api.github.com/repos/sebastianbergmann/complexity/zipball/739b35e53379900cc9ac327b2147867b8b6efd88";
          sha256 = "1y4yz8n8hszbhinf9ipx3pqyvgm7gz0krgyn19z0097yq3bbq8yf";
        };
      };
    };
    "sebastian/diff" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "sebastian-diff-3461e3fccc7cfdfc2720be910d3bd73c69be590d";
        src = fetchurl {
          url = "https://api.github.com/repos/sebastianbergmann/diff/zipball/3461e3fccc7cfdfc2720be910d3bd73c69be590d";
          sha256 = "0967nl6cdnr0v0z83w4xy59agn60kfv8gb41aw3fpy1n2wpp62dj";
        };
      };
    };
    "sebastian/environment" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "sebastian-environment-830c43a844f1f8d5b7a1f6d6076b784454d8b7ed";
        src = fetchurl {
          url = "https://api.github.com/repos/sebastianbergmann/environment/zipball/830c43a844f1f8d5b7a1f6d6076b784454d8b7ed";
          sha256 = "02045n3in01zk571v1phyhj0b2mvnvx8qnlqvw4j33r7qdd4clzn";
        };
      };
    };
    "sebastian/exporter" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "sebastian-exporter-ac230ed27f0f98f597c8a2b6eb7ac563af5e5b9d";
        src = fetchurl {
          url = "https://api.github.com/repos/sebastianbergmann/exporter/zipball/ac230ed27f0f98f597c8a2b6eb7ac563af5e5b9d";
          sha256 = "1a6yj8v8rwj3igip8xysdifvbd7gkzmwrj9whdx951pdq7add46j";
        };
      };
    };
    "sebastian/global-state" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "sebastian-global-state-0ca8db5a5fc9c8646244e629625ac486fa286bf2";
        src = fetchurl {
          url = "https://api.github.com/repos/sebastianbergmann/global-state/zipball/0ca8db5a5fc9c8646244e629625ac486fa286bf2";
          sha256 = "1csrfa5b7ivza712lfmbywp9jhwf4ls5lc0vn812xljkj7w24kg1";
        };
      };
    };
    "sebastian/lines-of-code" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "sebastian-lines-of-code-c1c2e997aa3146983ed888ad08b15470a2e22ecc";
        src = fetchurl {
          url = "https://api.github.com/repos/sebastianbergmann/lines-of-code/zipball/c1c2e997aa3146983ed888ad08b15470a2e22ecc";
          sha256 = "0fay9s5cm16gbwr7qjihwrzxn7sikiwba0gvda16xng903argbk0";
        };
      };
    };
    "sebastian/object-enumerator" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "sebastian-object-enumerator-5c9eeac41b290a3712d88851518825ad78f45c71";
        src = fetchurl {
          url = "https://api.github.com/repos/sebastianbergmann/object-enumerator/zipball/5c9eeac41b290a3712d88851518825ad78f45c71";
          sha256 = "11853z07w8h1a67wsjy3a6ir5x7khgx6iw5bmrkhjkiyvandqcn1";
        };
      };
    };
    "sebastian/object-reflector" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "sebastian-object-reflector-b4f479ebdbf63ac605d183ece17d8d7fe49c15c7";
        src = fetchurl {
          url = "https://api.github.com/repos/sebastianbergmann/object-reflector/zipball/b4f479ebdbf63ac605d183ece17d8d7fe49c15c7";
          sha256 = "0g5m1fswy6wlf300x1vcipjdljmd3vh05hjqhqfc91byrjbk4rsg";
        };
      };
    };
    "sebastian/recursion-context" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "sebastian-recursion-context-e75bd0f07204fec2a0af9b0f3cfe97d05f92efc1";
        src = fetchurl {
          url = "https://api.github.com/repos/sebastianbergmann/recursion-context/zipball/e75bd0f07204fec2a0af9b0f3cfe97d05f92efc1";
          sha256 = "1ag6ysxffhxyg7g4rj9xjjlwq853r4x92mmin4f09hn5mqn9f0l1";
        };
      };
    };
    "sebastian/resource-operations" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "sebastian-resource-operations-0f4443cb3a1d92ce809899753bc0d5d5a8dd19a8";
        src = fetchurl {
          url = "https://api.github.com/repos/sebastianbergmann/resource-operations/zipball/0f4443cb3a1d92ce809899753bc0d5d5a8dd19a8";
          sha256 = "0p5s8rp7mrhw20yz5wx1i4k8ywf0h0ximcqan39n9qnma1dlnbyr";
        };
      };
    };
    "sebastian/type" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "sebastian-type-75e2c2a32f5e0b3aef905b9ed0b179b953b3d7c7";
        src = fetchurl {
          url = "https://api.github.com/repos/sebastianbergmann/type/zipball/75e2c2a32f5e0b3aef905b9ed0b179b953b3d7c7";
          sha256 = "0bvfvb62qbpy2hzxs4bjzb0xhks6h3cp6qx96z4qlyz6wl2fa1w5";
        };
      };
    };
    "sebastian/version" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "sebastian-version-c6c1022351a901512170118436c764e473f6de8c";
        src = fetchurl {
          url = "https://api.github.com/repos/sebastianbergmann/version/zipball/c6c1022351a901512170118436c764e473f6de8c";
          sha256 = "1bs7bwa9m0fin1zdk7vqy5lxzlfa9la90lkl27sn0wr00m745ig1";
        };
      };
    };
    "theseer/tokenizer" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "theseer-tokenizer-34a41e998c2183e22995f158c581e7b5e755ab9e";
        src = fetchurl {
          url = "https://api.github.com/repos/theseer/tokenizer/zipball/34a41e998c2183e22995f158c581e7b5e755ab9e";
          sha256 = "1za4a017kjb4rw2ydglip4bp5q2y7mfiycj3fvnp145i84jc7n0q";
        };
      };
    };
  };
in
composerEnv.buildPackage {
  inherit packages devPackages noDev;
  name = "sandreas-m4b-tool";
  src = composerEnv.filterSrc ./.;
  executable = true;
  symlinkDependencies = false;
  meta = {};
}
