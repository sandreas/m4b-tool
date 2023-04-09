{
  description = "A wrapper for ffmpeg and mp4v2 to merge, split, and manipulate audiobooks";

  inputs = {
    nixpkgs.url = github:NixOS/nixpkgs/nixos-unstable;
    flake-utils.url = github:numtide/flake-utils;
  };

  outputs = { self, nixpkgs, flake-utils }:
    {
      overlay = nixpkgs.lib.composeManyExtensions [
        (final: prev: {
          m4b-tool = final.callPackage ./default.nix {};
        })
      ];
    } // (flake-utils.lib.eachDefaultSystem (system:
      let
        pkgs = import nixpkgs {
          inherit system;
          overlays = [ self.overlay ];
        };

        composer2NixSrc = pkgs.fetchFromGitHub {
          owner = "svanderburg";
          repo = "composer2nix";
          rev = "v0.0.6";
          sha256 = "sha256-P3acfGwHYjjZQcviPiOT7T7qzzP/drc2mibzrsrNP18=";
        };

        composer2Nix = import composer2NixSrc {
          inherit pkgs system;
        };
      in
      rec {
        packages = rec {
          m4b-tool = pkgs.m4b-tool;
          m4b-tool-libfdk = m4b-tool.override {
            useLibfdkFfmpeg = true;
          };
          default = m4b-tool;
        };

        apps = rec {
          m4b-tool = flake-utils.lib.mkApp { drv = self.packages.${system}.m4b-tool; };
          default = m4b-tool;
        };

        devShell = pkgs.mkShell {
          buildInputs = [
            composer2Nix
          ] ++ pkgs.m4b-tool.dependencies ++ pkgs.m4b-tool.devDependencies;
        };
      }));
}
