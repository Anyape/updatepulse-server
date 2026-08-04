#!/usr/bin/env bash

set -Eeuo pipefail

SCRIPT_NAME="$(basename "$0")"
START_DIR="$(pwd -P)"
readonly SCRIPT_NAME
readonly START_DIR

SVNUSER=""
DIR=""
PLUGINSLUG=""
MAINFILE=""
GITBRANCH="main"
COMMITMSG=""
DEPLOY=false
VERBOSE=false
SKIP_ASSETS=false
SKIP_GITHUB=false
TEMP_ROOT=""
CURRENTBRANCH=""
TAG_CREATED=false
TAG_PUSHED=false

usage() {
	cat <<EOF
Deploy a WordPress plugin to GitHub and the WordPress.org plugin repository.

Dry-run mode is enabled by default. Use --deploy to publish.

Usage:
  ./$SCRIPT_NAME <svn-user> [options]

Options:
  -d,  --deploy                 Publish the release.
  -v,  --verbose                Print commands before executing them.
  -sa, --skip-assets            Do not pause for WordPress.org asset changes.
       --skip-github            Skip GitHub release creation.
  -b,  --branch <branch>        Release branch (default: main).
  -m,  --message <message>      Release commit and SVN commit message.
  -mf, --mainfile <file.php>    Main plugin file (default: <slug>.php).
  -p,  --path <plugin-path>     Plugin Git repository (default: current directory).
  -s,  --slug <plugin-slug>     WordPress.org plugin slug (default: directory name).
  -h,  --help                   Show this help.
EOF
}

die() {
	echo "Error: $*" >&2
	exit 1
}

warn() {
	echo "Warning: $*" >&2
}

log_command() {
	if $VERBOSE; then
		printf '+'
		printf ' %q' "$@"
		printf '\n'
	fi
}

run() {
	log_command "$@"
	"$@"
}

cleanup() {
	local exit_code=$?

	trap - EXIT INT TERM

	if [[ -n "$TEMP_ROOT" && -d "$TEMP_ROOT" ]]; then
		rm -rf -- "$TEMP_ROOT"
	fi

	if [[ $exit_code -ne 0 ]] && $TAG_CREATED && ! $TAG_PUSHED; then
		git -C "$DIR" tag -d "$GIT_TAG" >/dev/null 2>&1 || warn "Unable to remove unpublished local tag '$GIT_TAG'."
	fi

	if [[ -n "$CURRENTBRANCH" ]]; then
		local active_branch
		active_branch=$(git -C "$DIR" symbolic-ref --quiet --short HEAD 2>/dev/null || true)

		if [[ "$active_branch" != "$CURRENTBRANCH" ]]; then
			if ! git -C "$DIR" checkout --quiet "$CURRENTBRANCH"; then
				warn "Unable to restore branch '$CURRENTBRANCH'."
			fi
		fi
	fi

	exit "$exit_code"
}

trap cleanup EXIT
trap 'exit 130' INT TERM

require_value() {
	local option="$1"
	local value="${2:-}"

	if [[ -z "$value" || "$value" == -* ]]; then
		die "Missing value for $option."
	fi
}

while [[ $# -gt 0 ]]; do
	case "$1" in
		-d|--deploy)
			DEPLOY=true
			shift
			;;
		-v|--verbose)
			VERBOSE=true
			shift
			;;
		-sa|--skip-assets)
			SKIP_ASSETS=true
			shift
			;;
		--skip-github)
			SKIP_GITHUB=true
			shift
			;;
		-b|--branch)
			require_value "$1" "${2:-}"
			GITBRANCH="$2"
			shift 2
			;;
		-m|--message)
			require_value "$1" "${2:-}"
			COMMITMSG="$2"
			shift 2
			;;
		-mf|--mainfile)
			require_value "$1" "${2:-}"
			MAINFILE="$2"
			shift 2
			;;
		-p|--path)
			require_value "$1" "${2:-}"
			DIR="$2"
			shift 2
			;;
		-s|--slug)
			require_value "$1" "${2:-}"
			PLUGINSLUG="$2"
			shift 2
			;;
		-h|--help)
			usage
			exit 0
			;;
		--)
			shift
			break
			;;
		-*)
			die "Unknown option '$1'."
			;;
		*)
			if [[ -n "$SVNUSER" ]]; then
				die "Unexpected argument '$1'."
			fi

			SVNUSER="$1"
			shift
			;;
	esac
done

[[ $# -eq 0 ]] || die "Unexpected argument '$1'."
[[ -n "$SVNUSER" ]] || die "Missing required parameter <svn-user>."

DIR="${DIR:-$START_DIR}"
[[ -d "$DIR" ]] || die "Plugin path '$DIR' does not exist."
DIR="$(cd "$DIR" && pwd -P)"
PLUGINSLUG="${PLUGINSLUG:-$(basename "$DIR")}"
MAINFILE="${MAINFILE:-$PLUGINSLUG.php}"

[[ "$PLUGINSLUG" =~ ^[a-z0-9][a-z0-9-]*$ ]] || die "Invalid plugin slug '$PLUGINSLUG'."
[[ "$MAINFILE" != */* && "$MAINFILE" == *.php ]] || die "Main plugin file must be a PHP filename in the plugin root."

readonly GITPATH="$DIR"
readonly SVNURL="https://plugins.svn.wordpress.org/$PLUGINSLUG"
readonly DISTIGNORE="$GITPATH/.distignore"

for command in git svn rsync tar zip awk grep; do
	command -v "$command" >/dev/null 2>&1 || die "Command '$command' not found."
done

[[ -f "$GITPATH/readme.txt" ]] || die "Missing '$GITPATH/readme.txt'."
[[ -f "$GITPATH/$MAINFILE" ]] || die "Missing '$GITPATH/$MAINFILE'."
[[ -f "$DISTIGNORE" ]] || die "Missing distribution manifest '$DISTIGNORE'."

GIT_TOPLEVEL=$(git -C "$GITPATH" rev-parse --show-toplevel 2>/dev/null) || die "'$GITPATH' is not a Git repository."
[[ "$GIT_TOPLEVEL" == "$GITPATH" ]] || die "Plugin path must be the Git repository root ('$GIT_TOPLEVEL')."

CURRENTBRANCH=$(git -C "$GITPATH" symbolic-ref --quiet --short HEAD) || die "Deployments cannot start from a detached HEAD."
git -C "$GITPATH" show-ref --verify --quiet "refs/heads/$GITBRANCH" || die "Branch '$GITBRANCH' does not exist locally."

read_versions() {
	local readme_version
	local plugin_version

	readme_version=$(awk '
		/^Stable tag:[[:space:]]*/ {
			value = $0
			sub( /^Stable tag:[[:space:]]*/, "", value )
			print value
			count++
		}
		END { if ( 1 != count ) exit 1 }
	' "$GITPATH/readme.txt") || die "readme.txt must contain exactly one Stable tag."

	plugin_version=$(awk '
		/^[[:space:]]*\*?[[:space:]]*Version[[:space:]]*:/ {
			value = $0
			sub( /^[^:]*:[[:space:]]*/, "", value )
			print value
			count++
		}
		END { if ( 1 != count ) exit 1 }
	' "$GITPATH/$MAINFILE") || die "$MAINFILE must contain exactly one Version header."

	[[ -n "$readme_version" && "$readme_version" == "$plugin_version" ]] || die "Versions in readme.txt ('$readme_version') and $MAINFILE ('$plugin_version') do not match."
	[[ "$readme_version" =~ ^[0-9]+(\.[0-9]+)+([.-][0-9A-Za-z.-]+)?$ ]] || die "Invalid release version '$readme_version'."

	VERSION="$readme_version"
	GIT_TAG="v$VERSION"
	SVN_TAG="$VERSION"
}

read_versions
readonly PLANNED_VERSION="$VERSION"
readonly PLANNED_GIT_TAG="$GIT_TAG"

LOCAL_BRANCH_COMMIT=$(git -C "$GITPATH" rev-parse "refs/heads/$GITBRANCH")
REMOTE_BRANCH_LINE=$(git -C "$GITPATH" ls-remote --heads origin "refs/heads/$GITBRANCH") || die "Unable to read origin/$GITBRANCH."
REMOTE_BRANCH_COMMIT=${REMOTE_BRANCH_LINE%%[[:space:]]*}
[[ -n "$REMOTE_BRANCH_COMMIT" ]] || die "Remote branch 'origin/$GITBRANCH' does not exist."
[[ "$LOCAL_BRANCH_COMMIT" == "$REMOTE_BRANCH_COMMIT" ]] || die "Local '$GITBRANCH' does not match origin/$GITBRANCH. Update it before releasing."

git -C "$GITPATH" show-ref --tags --quiet --verify "refs/tags/$GIT_TAG" && die "Git tag '$GIT_TAG' already exists locally."

set +e
git -C "$GITPATH" ls-remote --exit-code --tags origin "refs/tags/$GIT_TAG" >/dev/null 2>&1
REMOTE_TAG_STATUS=$?
set -e

if [[ $REMOTE_TAG_STATUS -eq 0 ]]; then
	die "Git tag '$GIT_TAG' already exists on origin."
elif [[ $REMOTE_TAG_STATUS -ne 2 ]]; then
	die "Unable to check Git tag '$GIT_TAG' on origin."
fi

SVN_TAGS=$(svn list "$SVNURL/tags/") || die "Unable to read WordPress.org tags at '$SVNURL/tags/'."
if grep -Fqx "$SVN_TAG/" <<< "$SVN_TAGS"; then
	die "WordPress.org tag '$SVN_TAG' already exists."
fi

CREATE_GITHUB_RELEASE=false
if ! $SKIP_GITHUB; then
	if ! command -v gh >/dev/null 2>&1; then
		warn "Command 'gh' not found; the GitHub release will be skipped."
	elif ! gh auth status >/dev/null 2>&1; then
		warn "GitHub CLI is not authenticated; the GitHub release will be skipped."
	else
		CREATE_GITHUB_RELEASE=true
	fi
fi

echo ".........................................."
echo
if $DEPLOY; then
	echo "Deployment"
else
	echo "Dry run"
fi
echo
echo "Plugin:             $PLUGINSLUG"
echo "Version:            $VERSION"
echo "Git tag:            $GIT_TAG"
echo "Release branch:     $GITBRANCH"
echo "Starting branch:    $CURRENTBRANCH"
echo "Plugin path:        $GITPATH"
echo "WordPress.org URL:  $SVNURL"
echo "GitHub release:     $CREATE_GITHUB_RELEASE"
echo "Update assets:      $(! $SKIP_ASSETS && echo true || echo false)"
echo
echo "Working tree payload to commit on '$GITBRANCH':"
git -C "$GITPATH" status --short
echo ".........................................."

git -C "$GITPATH" diff --check
git -C "$GITPATH" diff --cached --check

if ! $DEPLOY; then
	echo
	echo "Dry-run complete. No branches, tags, working copies, archives, or remote repositories were changed."
	exit 0
fi

if [[ -z "$COMMITMSG" ]]; then
	[[ -t 0 ]] || die "A commit message is required in non-interactive mode; use --message."
	read -r -p "Release commit message: " COMMITMSG
fi
[[ -n "${COMMITMSG//[[:space:]]/}" ]] || die "The commit message cannot be empty."

if [[ -t 0 ]]; then
	read -r -p "Commit the payload shown above to '$GITBRANCH' and publish $GIT_TAG? [y/N] " confirmation
	[[ "$confirmation" == "y" || "$confirmation" == "Y" ]] || die "Deployment cancelled."
else
	die "Interactive confirmation is required for deployment."
fi

run git -C "$GITPATH" checkout "$GITBRANCH"
read_versions
[[ "$VERSION" == "$PLANNED_VERSION" && "$GIT_TAG" == "$PLANNED_GIT_TAG" ]] || die "The release version changed after checking out '$GITBRANCH'."
run git -C "$GITPATH" add -A -- .

if git -C "$GITPATH" diff --cached --quiet; then
	echo "Notice: Nothing to commit; releasing the current '$GITBRANCH' HEAD."
else
	run git -C "$GITPATH" commit -m "$COMMITMSG"
fi

RELEASE_COMMIT=$(git -C "$GITPATH" rev-parse HEAD)
run git -C "$GITPATH" tag -a "$GIT_TAG" -m "Release $GIT_TAG" "$RELEASE_COMMIT"
TAG_CREATED=true

TEMP_ROOT=$(mktemp -d "/tmp/${PLUGINSLUG}-deploy.XXXXXX")
readonly SOURCE_PATH="$TEMP_ROOT/source"
readonly DIST_ROOT="$TEMP_ROOT/dist"
readonly DIST_PATH="$DIST_ROOT/$PLUGINSLUG"
readonly SVN_PATH="$TEMP_ROOT/svn"
readonly ZIP_PATH="$TEMP_ROOT/$PLUGINSLUG.zip"

run mkdir -p "$SOURCE_PATH" "$DIST_PATH"
log_command git -C "$GITPATH" archive --format=tar "$RELEASE_COMMIT"
git -C "$GITPATH" archive --format=tar "$RELEASE_COMMIT" | tar -xf - -C "$SOURCE_PATH"
run rsync --archive --delete --exclude-from="$DISTIGNORE" "$SOURCE_PATH/" "$DIST_PATH/"

(
	cd "$DIST_ROOT"
	log_command zip -q -r "$ZIP_PATH" "$PLUGINSLUG"
	zip -q -r "$ZIP_PATH" "$PLUGINSLUG"
)
run zip -T "$ZIP_PATH"

run svn checkout "$SVNURL" "$SVN_PATH"
run mkdir -p "$SVN_PATH/trunk" "$SVN_PATH/tags" "$SVN_PATH/assets"
run svn add --force --parents "$SVN_PATH/trunk" "$SVN_PATH/tags" "$SVN_PATH/assets"
run rsync --archive --delete --exclude=.svn/ "$DIST_PATH/" "$SVN_PATH/trunk/"

stage_svn_changes() {
	local target="$1"
	local status_output
	local status_line
	local status
	local path

	run svn add --force "$target"
	status_output=$(svn status "$target")

	while IFS= read -r status_line; do
		status=${status_line:0:1}
		path=${status_line:8}

		if [[ "$status" == "!" ]]; then
			run svn rm -- "$path"
		fi
	done <<< "$status_output"
}

stage_svn_changes "$SVN_PATH/trunk"
run svn copy "$SVN_PATH/trunk" "$SVN_PATH/tags/$SVN_TAG"

if ! $SKIP_ASSETS; then
	[[ -t 0 ]] || die "Asset updates require an interactive terminal; use --skip-assets when no changes are needed."
	echo "Adjust assets in '$SVN_PATH/assets', then press any key to continue."
	read -r -n 1 -s
	echo
	stage_svn_changes "$SVN_PATH/assets"
fi

echo "WordPress.org changes prepared:"
svn status "$SVN_PATH"

run git -C "$GITPATH" push --atomic origin "$GITBRANCH" "refs/tags/$GIT_TAG"
TAG_PUSHED=true
run svn commit "$SVN_PATH" --username "$SVNUSER" -m "$COMMITMSG"

if $CREATE_GITHUB_RELEASE; then
	if ! (
		cd "$GITPATH"
		run gh release create "$GIT_TAG" \
			--target "$RELEASE_COMMIT" \
			--title "Release $GIT_TAG" \
			--notes "Auto-deployed from commit $RELEASE_COMMIT" \
			"$ZIP_PATH"
	); then
		warn "GitHub release creation failed. Git tag '$GIT_TAG' and WordPress.org tag '$SVN_TAG' were already published."
		exit 1
	fi
fi

echo "Deployment complete: $GIT_TAG ($RELEASE_COMMIT)."
