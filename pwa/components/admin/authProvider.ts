import { AuthProvider } from "react-admin";

const authProvider: AuthProvider = {
  login: ({ username, password }) => {
    const request = new Request(`${window.origin}/auth`, {
      method: "POST",
      body: JSON.stringify({ email: username, password }),
      headers: new Headers({ "Content-Type": "application/json" }),
    });
    return fetch(request)
      .then((response) => {
        if (response.status < 200 || response.status >= 300) {
          throw new Error(response.statusText);
        }
        return response.json();
      })
      .then(({ token }) => {
        localStorage.setItem("token", token);
      })
      .catch(() => {
        throw new Error("Network error");
      });
  },
  logout: () => {
    localStorage.removeItem("token");
    return Promise.resolve();
  },
  checkAuth: () =>
    localStorage.getItem("token") ? Promise.resolve() : Promise.reject(),
  checkError: (error) => {
    const status = error.status;
    if (status === 401 || status === 403) {
      localStorage.removeItem("token");
      return Promise.reject();
    }
    return Promise.resolve();
  },
  getIdentity: () => Promise.resolve(),
  getPermissions: () => Promise.resolve(),
};

export default authProvider;
