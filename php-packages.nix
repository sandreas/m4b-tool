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
        name = "doctrine-collections-d8af7f248c74f195f7347424600fd9e17b57af59";
        src = fetchurl {
          url = "https://api.github.com/repos/doctrine/collections/zipball/d8af7f248c74f195f7347424600fd9e17b57af59";
          sha256 = "1xzwwaxhzhjz2xx8bq9k0gvfg6aj6yn1fza2a16d4ahzmrbbvyvm";
        };
      };
    };
    "doctrine/deprecations" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "doctrine-deprecations-dfbaa3c2d2e9a9df1118213f3b8b0c597bb99fab";
        src = fetchurl {
          url = "https://api.github.com/repos/doctrine/deprecations/zipball/dfbaa3c2d2e9a9df1118213f3b8b0c597bb99fab";
          sha256 = "1qydhnf94wgjlrgzydjcz31rr5f87pg3vlkkd0gynggw1ycgkkcg";
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
        name = "psr-cache-aa5030cfa5405eccfdcb1083ce040c2cb8d253bf";
        src = fetchurl {
          url = "https://api.github.com/repos/php-fig/cache/zipball/aa5030cfa5405eccfdcb1083ce040c2cb8d253bf";
          sha256 = "07rnyjwb445sfj30v5ny3gfsgc1m7j7cyvwjgs2cm9slns1k1ml8";
        };
      };
    };
    "psr/container" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "psr-container-c71ecc56dfe541dbd90c5360474fbc405f8d5963";
        src = fetchurl {
          url = "https://api.github.com/repos/php-fig/container/zipball/c71ecc56dfe541dbd90c5360474fbc405f8d5963";
          sha256 = "1mvan38yb65hwk68hl0p7jymwzr4zfnaxmwjbw7nj3rsknvga49i";
        };
      };
    };
    "psr/http-message" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "psr-http-message-402d35bcb92c70c026d1a6a9883f06b2ead23d71";
        src = fetchurl {
          url = "https://api.github.com/repos/php-fig/http-message/zipball/402d35bcb92c70c026d1a6a9883f06b2ead23d71";
          sha256 = "13cnlzrh344n00sgkrp5cgbkr8dznd99c3jfnpl0wg1fdv1x4qfm";
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
        name = "symfony-cache-23b61c9592ee72233c31625f0ae805dd1571e928";
        src = fetchurl {
          url = "https://api.github.com/repos/symfony/cache/zipball/23b61c9592ee72233c31625f0ae805dd1571e928";
          sha256 = "1myl1zfd39x42anp87yw2k6a0m55hqi1f0dp29y9fikqpk0vbby5";
        };
      };
    };
    "symfony/cache-contracts" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "symfony-cache-contracts-df6a1a44c890faded49a5fca33c2d5c5fd3c2197";
        src = fetchurl {
          url = "https://api.github.com/repos/symfony/cache-contracts/zipball/df6a1a44c890faded49a5fca33c2d5c5fd3c2197";
          sha256 = "0bxpc6vqdmxw470khf8f2dqb8aagn82ff5nzqqvn7b1j5qnp70k2";
        };
      };
    };
    "symfony/console" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "symfony-console-3284aafcac338b6e86fd955ee4d794cbe434151a";
        src = fetchurl {
          url = "https://api.github.com/repos/symfony/console/zipball/3284aafcac338b6e86fd955ee4d794cbe434151a";
          sha256 = "08svrsgjb4z081l2zf3d5knmnlwszbp45yplhgvdb7qcnhk4g1j0";
        };
      };
    };
    "symfony/deprecation-contracts" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "symfony-deprecation-contracts-0e0d29ce1f20deffb4ab1b016a7257c4f1e789a1";
        src = fetchurl {
          url = "https://api.github.com/repos/symfony/deprecation-contracts/zipball/0e0d29ce1f20deffb4ab1b016a7257c4f1e789a1";
          sha256 = "1qhyyfyd7q75nyqivjzrljmqa5qhh09gjs2vz7s3xadq0j525c2b";
        };
      };
    };
    "symfony/polyfill-ctype" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "symfony-polyfill-ctype-a3cc8b044a6ea513310cbd48ef7333b384945638";
        src = fetchurl {
          url = "https://api.github.com/repos/symfony/polyfill-ctype/zipball/a3cc8b044a6ea513310cbd48ef7333b384945638";
          sha256 = "1gwalz2r31bfqldkqhw8cbxybmc1pkg74kvg07binkhk531gjqdn";
        };
      };
    };
    "symfony/polyfill-intl-grapheme" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "symfony-polyfill-intl-grapheme-b9123926e3b7bc2f98c02ad54f6a4b02b91a8abe";
        src = fetchurl {
          url = "https://api.github.com/repos/symfony/polyfill-intl-grapheme/zipball/b9123926e3b7bc2f98c02ad54f6a4b02b91a8abe";
          sha256 = "06kbz2rqp0kyxpry055pciv02ihl7vgygigqhdqqy6q7aphg9i9a";
        };
      };
    };
    "symfony/polyfill-intl-normalizer" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "symfony-polyfill-intl-normalizer-3833d7255cc303546435cb650316bff708a1c75c";
        src = fetchurl {
          url = "https://api.github.com/repos/symfony/polyfill-intl-normalizer/zipball/3833d7255cc303546435cb650316bff708a1c75c";
          sha256 = "0qrq26nw97xfcl0p8x4ria46lk47k73vjjyqiklpw8w5cbibsfxj";
        };
      };
    };
    "symfony/polyfill-mbstring" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "symfony-polyfill-mbstring-85181ba99b2345b0ef10ce42ecac37612d9fd341";
        src = fetchurl {
          url = "https://api.github.com/repos/symfony/polyfill-mbstring/zipball/85181ba99b2345b0ef10ce42ecac37612d9fd341";
          sha256 = "07ir4drsx4ddmbps6igm6wyrzx2ksz3d61rs2m7p27qz7kx17ff1";
        };
      };
    };
    "symfony/polyfill-php81" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "symfony-polyfill-php81-4a4cfc2d253c21a5ad0e53071df248ed48c6ce5c";
        src = fetchurl {
          url = "https://api.github.com/repos/symfony/polyfill-php81/zipball/4a4cfc2d253c21a5ad0e53071df248ed48c6ce5c";
          sha256 = "01s1x2ak9c3idpigbdx7y6a9h2mfplh53131z0mr48wh9azn2s5q";
        };
      };
    };
    "symfony/process" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "symfony-process-9b8a40b7289767aa7117e957573c2a535efe6585";
        src = fetchurl {
          url = "https://api.github.com/repos/symfony/process/zipball/9b8a40b7289767aa7117e957573c2a535efe6585";
          sha256 = "10cwgc31hrc9ahf67g1zg397zx2jl4y8hlhg44dyj8y9g1kbna77";
        };
      };
    };
    "symfony/service-contracts" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "symfony-service-contracts-bd1d9e59a81d8fa4acdcea3f617c581f7475a80f";
        src = fetchurl {
          url = "https://api.github.com/repos/symfony/service-contracts/zipball/bd1d9e59a81d8fa4acdcea3f617c581f7475a80f";
          sha256 = "1q7382ingrvqdh7hm8lrwrimcvlv5qcmp6xrparfh1kmrsf45prv";
        };
      };
    };
    "symfony/string" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "symfony-string-61b72d66bf96c360a727ae6232df5ac83c71f626";
        src = fetchurl {
          url = "https://api.github.com/repos/symfony/string/zipball/61b72d66bf96c360a727ae6232df5ac83c71f626";
          sha256 = "087cyb1fiiyqc4dympidnqiabnpns8iwp8wg0glq6c82syp4940d";
        };
      };
    };
    "symfony/var-exporter" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "symfony-var-exporter-90173ef89c40e7c8c616653241048705f84130ef";
        src = fetchurl {
          url = "https://api.github.com/repos/symfony/var-exporter/zipball/90173ef89c40e7c8c616653241048705f84130ef";
          sha256 = "1rwjyj000lx2nlcmw4l9kj5hcfldi90w9mdlyy4fwfgsbrcsiq2p";
        };
      };
    };
    "twig/twig" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "twig-twig-0b6f9d8370bb3b7f1ce5313ed8feb0fafd6e399a";
        src = fetchurl {
          url = "https://api.github.com/repos/twigphp/Twig/zipball/0b6f9d8370bb3b7f1ce5313ed8feb0fafd6e399a";
          sha256 = "0vkvamm6ca4vxj62c1b9d9sc2djc97jh093zqrnf9vy3rzr9cg2k";
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
        name = "wapmorgan-mp3info-f4e3b0d606732f667729c116e1baa4f845a65f9d";
        src = fetchurl {
          url = "https://api.github.com/repos/wapmorgan/Mp3Info/zipball/f4e3b0d606732f667729c116e1baa4f845a65f9d";
          sha256 = "02vhjlz8v7j7mwgp7yjzcjiv8j1r7zkwh8pw12kx4s5ji285vf94";
        };
      };
    };
  };
  devPackages = {
    "bamarni/composer-bin-plugin" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "bamarni-composer-bin-plugin-92fd7b1e6e9cdae19b0d57369d8ad31a37b6a880";
        src = fetchurl {
          url = "https://api.github.com/repos/bamarni/composer-bin-plugin/zipball/92fd7b1e6e9cdae19b0d57369d8ad31a37b6a880";
          sha256 = "15yv6k6rxnpzvxbmnasy31hylq2dnggllfz7hkd0901zwq3flsw4";
        };
      };
    };
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
        name = "mikey179-vfsstream-fe695ec993e0a55c3abdda10a9364eb31c6f1bf0";
        src = fetchurl {
          url = "https://api.github.com/repos/bovigo/vfsStream/zipball/fe695ec993e0a55c3abdda10a9364eb31c6f1bf0";
          sha256 = "0f0ninp28fiwpv5da4yzjha0x57a46mkzxj504nqlgmzv9f85bdv";
        };
      };
    };
    "mockery/mockery" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "mockery-mockery-1f4efdd7d3beafe9807b08156dfcb176d18f1699";
        src = fetchurl {
          url = "https://api.github.com/repos/mockery/mockery/zipball/1f4efdd7d3beafe9807b08156dfcb176d18f1699";
          sha256 = "1vdmk2yp8kg8lhf78p3dnqgfii5zks56viabv84javdpm9zqah6k";
        };
      };
    };
    "myclabs/deep-copy" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "myclabs-deep-copy-123267b2c49fbf30d78a7b2d333f6be754b94845";
        src = fetchurl {
          url = "https://api.github.com/repos/myclabs/DeepCopy/zipball/123267b2c49fbf30d78a7b2d333f6be754b94845";
          sha256 = "16lmcqqvxjg8rbbsav0csa1m22b7wv566ks6dry9jdqd3k8cfi5w";
        };
      };
    };
    "nikic/php-parser" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "nikic-php-parser-8eea230464783aa9671db8eea6f8c6ac5285794b";
        src = fetchurl {
          url = "https://api.github.com/repos/nikic/PHP-Parser/zipball/8eea230464783aa9671db8eea6f8c6ac5285794b";
          sha256 = "1afglrsixmhjb4s5zpvw1y1mayjii6fbb22cfb6c96i84d3sjlc6";
        };
      };
    };
    "phar-io/manifest" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "phar-io-manifest-54750ef60c58e43759730615a392c31c80e23176";
        src = fetchurl {
          url = "https://api.github.com/repos/phar-io/manifest/zipball/54750ef60c58e43759730615a392c31c80e23176";
          sha256 = "0xas0i7jd6w4hknfmbwdswpzngblm3d884hy3rba0q2cs928ndml";
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
        name = "phpunit-php-code-coverage-85402a822d1ecf1db1096959413d35e1c37cf1a5";
        src = fetchurl {
          url = "https://api.github.com/repos/sebastianbergmann/php-code-coverage/zipball/85402a822d1ecf1db1096959413d35e1c37cf1a5";
          sha256 = "04bj7hqydv7r2dnfsyayg37f1f5x0bv7m5b1vmnl50zlz2a1dl8j";
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
        name = "phpunit-phpunit-de6abf3b6f8dd955fac3caad3af7a9504e8c2ffa";
        src = fetchurl {
          url = "https://api.github.com/repos/sebastianbergmann/phpunit/zipball/de6abf3b6f8dd955fac3caad3af7a9504e8c2ffa";
          sha256 = "1f9blzdpnvfksgq6gqm2adhyffxpsyw9f8qb1d06ldm2d6f7nlfw";
        };
      };
    };
    "sebastian/cli-parser" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "sebastian-cli-parser-2b56bea83a09de3ac06bb18b92f068e60cc6f50b";
        src = fetchurl {
          url = "https://api.github.com/repos/sebastianbergmann/cli-parser/zipball/2b56bea83a09de3ac06bb18b92f068e60cc6f50b";
          sha256 = "18rr5nj0dm4wmfppybdrs2pfkzy5nabb1lik9r9a661f926q8xv9";
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
        name = "sebastian-complexity-25f207c40d62b8b7aa32f5ab026c53561964053a";
        src = fetchurl {
          url = "https://api.github.com/repos/sebastianbergmann/complexity/zipball/25f207c40d62b8b7aa32f5ab026c53561964053a";
          sha256 = "1k8w6z8zcym3y5s0riami9667s0gd206jr3za6pkbb90zzj6b76g";
        };
      };
    };
    "sebastian/diff" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "sebastian-diff-ba01945089c3a293b01ba9badc29ad55b106b0bc";
        src = fetchurl {
          url = "https://api.github.com/repos/sebastianbergmann/diff/zipball/ba01945089c3a293b01ba9badc29ad55b106b0bc";
          sha256 = "1c5xr3mfcf7jzrj0grbc7lapi60j42dcwjsjs1x8kn5willmz9mp";
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
        name = "sebastian-exporter-78c00df8f170e02473b682df15bfcdacc3d32d72";
        src = fetchurl {
          url = "https://api.github.com/repos/sebastianbergmann/exporter/zipball/78c00df8f170e02473b682df15bfcdacc3d32d72";
          sha256 = "1nbx0w1q50hv47k4hf7k17c3lwi8zbm6qm8v2rmy3qkh84z5ygi5";
        };
      };
    };
    "sebastian/global-state" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "sebastian-global-state-bca7df1f32ee6fe93b4d4a9abbf69e13a4ada2c9";
        src = fetchurl {
          url = "https://api.github.com/repos/sebastianbergmann/global-state/zipball/bca7df1f32ee6fe93b4d4a9abbf69e13a4ada2c9";
          sha256 = "1sixfy1wp9bf2jx92pbvv0w5swnisga4klnpw6dgmpvcscvqqsdl";
        };
      };
    };
    "sebastian/lines-of-code" = {
      targetDir = "";
      src = composerEnv.buildZipPackage {
        name = "sebastian-lines-of-code-e1e4a170560925c26d424b6a03aed157e7dcc5c5";
        src = fetchurl {
          url = "https://api.github.com/repos/sebastianbergmann/lines-of-code/zipball/e1e4a170560925c26d424b6a03aed157e7dcc5c5";
          sha256 = "1ycasbrcsmyqszihx730l9krh2inj72xkpvb2fqd5y5xn4r8va2g";
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
        name = "sebastian-resource-operations-05d5692a7993ecccd56a03e40cd7e5b09b1d404e";
        src = fetchurl {
          url = "https://api.github.com/repos/sebastianbergmann/resource-operations/zipball/05d5692a7993ecccd56a03e40cd7e5b09b1d404e";
          sha256 = "186kqdsgsrdyz4j2sv5lgjyr9ykhgbkv8gvkmaqdq99c11qfjin2";
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
        name = "theseer-tokenizer-737eda637ed5e28c3413cb1ebe8bb52cbf1ca7a2";
        src = fetchurl {
          url = "https://api.github.com/repos/theseer/tokenizer/zipball/737eda637ed5e28c3413cb1ebe8bb52cbf1ca7a2";
          sha256 = "1pi1wlzmyzla2wli0h3kqf8vhddhqra2bkp9rg81b38pbh791w34";
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
