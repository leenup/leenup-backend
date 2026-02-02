import {
  HydraAdmin,
  ResourceGuesser,
  fetchHydra as baseFetchHydra,
  hydraDataProvider as baseHydraDataProvider,
  useIntrospection,
} from "@api-platform/admin";
import type { HttpClientOptions } from "@api-platform/admin";
import { parseHydraDocumentation } from "@api-platform/api-doc-parser";
import authProvider from "./authProvider";
import { SkillList } from "./collection/SkillList";
import { SkillShow } from "./view/SkillShow";
import { CategoryShow } from "./view/CategoryShow";

const entrypoint = window.origin;

const getHeaders = (): HeadersInit => {
  const token = localStorage.getItem("token");

  return token ? { Authorization: `Bearer ${token}` } : {};
};

const fetchHydra = (url: URL | string, options: HttpClientOptions = {}) =>
  baseFetchHydra(typeof url === "string" ? new URL(url) : url, {
    ...options,
    headers: getHeaders,
  });

const RedirectToLogin = () => {
  const introspect = useIntrospection();

  if (localStorage.getItem("token")) {
    introspect();
    return <></>;
  }
  return <>Redirecting to login...</>;
};

const apiDocumentationParser = async (entrypoint: string) => {
  try {
    const headers = getHeaders();
    return await parseHydraDocumentation(entrypoint, { headers });
  } catch (result: any) {
    if (result?.status === 401) {
      return Promise.resolve({
        api: { entrypoint },
        response: result,
        status: result.status,
      });
    }
    throw result;
  }
};

const dataProvider = baseHydraDataProvider({
  entrypoint,
  httpClient: fetchHydra,
  apiDocumentationParser,
});

const App = () => (
  <HydraAdmin
    dataProvider={dataProvider}
    authProvider={authProvider}
    entrypoint={entrypoint}
    title="LeenUp Admin"
  >
    <ResourceGuesser name="categories" show={CategoryShow} />
    <ResourceGuesser name="skills" list={SkillList} show={SkillShow} />
    <ResourceGuesser name="users" />
  </HydraAdmin>
);

export default App;
