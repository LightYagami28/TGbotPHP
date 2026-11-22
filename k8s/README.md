# Kubernetes manifests — TGbotPHP

Manifests in this directory deploy two modes of the bot:

- `tgbotphp` — long-polling worker (no network port)
- `tgbotphp-webhook` — webhook server on port 8000, exposed via Ingress

## Contents

- `namespace.yaml` — dedicated namespace `tgbotphp` with Pod Security Standards `restricted`
- `configmap.yaml` — non-sensitive configuration
- `secret.yaml` — **template** with empty values; never commit real values
- `deployment.yaml` — Deployments for polling and webhook modes
- `service.yaml` — ClusterIP Service for the webhook (port 80 -> 8000)
- `ingress.yaml` — Ingress with mandatory HTTPS/TLS via cert-manager (webhook mode)
- `networkpolicy.yaml` — default-deny ingress/egress plus DNS, HTTPS egress and
  webhook ingress

## Prerequisites

- Kubernetes cluster with an Ingress controller (e.g. `ingress-nginx`)
- cert-manager installed with a `ClusterIssuer` named `letsencrypt-prod`
- Container image `ghcr.io/lightyagami28/tgbotphp:2.0.0` available to the cluster.
  `deployment.yaml` pins an immutable digest placeholder: replace the
  `sha256:...` value with the digest printed by your registry after the push
  (`docker buildx imagetools inspect <image>:<tag>`), in both Deployments.
  `imagePullPolicy: Always` keeps the digest fresh on every rollout.

## Hardening applied

- Pods run as non-root UID/GID 10001 with `fsGroup: 10001`, `RuntimeDefault`
  seccomp, `allowPrivilegeEscalation: false`, `readOnlyRootFilesystem: true`,
  all capabilities dropped, and a writable `emptyDir` only at `/tmp`.
- `automountServiceAccountToken: false`: the bot never calls the Kubernetes API.
- CPU/memory requests and limits are set on every container.
- The namespace enforces the `restricted` Pod Security Standard (audit + warn).
- The Ingress redirects all traffic to HTTPS and terminates TLS with cert-manager.
- The Service is `ClusterIP`: the webhook is reachable only through the Ingress.
- The Secret is mounted read-only at `/run/secrets` instead of being copied
  into environment variables; `Value::env()` falls back to those files only
  when the matching environment variable is absent.
- NetworkPolicy denies all traffic by default; only cluster DNS, HTTPS (443) to
  public addresses and port 8000 from the `ingress-nginx` namespace are allowed.
  The polling pod has no ingress at all.

## Deploy

1. Provide the three values from `secret.yaml` through your secret manager
   (External Secrets, Sealed Secrets, SOPS) or create the Secret locally and
   keep it out of version control. `TELEGRAM_SECRET_TOKEN` is required for
   webhook mode.
2. Apply the manifests:

```bash
kubectl apply -f k8s/namespace.yaml
kubectl apply -f k8s/configmap.yaml
kubectl apply -f k8s/secret.yaml
kubectl apply -f k8s/deployment.yaml
kubectl apply -f k8s/service.yaml
kubectl apply -f k8s/ingress.yaml
kubectl apply -f k8s/networkpolicy.yaml
```

Or apply the whole folder at once:

```bash
kubectl apply -f k8s/
```

1. Register the webhook with Telegram (replace host):

```bash
TELEGRAM_BOT_TOKEN=... vendor/bin/tgbot webhook:set \
  --url=https://tgbotphp.example.com/examples/webhook.php \
  --secret="$TELEGRAM_SECRET_TOKEN"
```

1. Verify:

```bash
kubectl -n tgbotphp get pods,svc,ingress
kubectl -n tgbotphp logs deploy/tgbotphp-webhook
```

## Local Docker Compose

`docker-compose.yml` builds the same pinned image, binds the webhook port to
`127.0.0.1` only (put an HTTPS reverse proxy in front), requires the token and
secret token via environment variables, and sets the same resource limits,
read-only root filesystem, dropped capabilities and healthchecks.

## Notes

- Run only one mode at a time if the same bot token is used, or use
  separate tokens, to avoid duplicate update processing.
- The webhook container uses a read-only root filesystem with a writable
  `/tmp` (emptyDir) for the file cache.
- No PVC is required: state is kept in Telegram and in the `/tmp` cache.
- For Helm-based installs, package these manifests into a chart or use
  `helm create` and move the resources under `templates/`. No chart is shipped
  in this repository.
